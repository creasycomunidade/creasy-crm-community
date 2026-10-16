<?php

namespace App\Modules\Integrations\Services;

use App\Models\Workspace;
use App\Modules\Ads\Models\AdConnection;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Modules\Shared\Models\ChannelAccount;
use App\Modules\Social\Models\SocialAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ZernioWorkspaceManager
{
    public function provision(Workspace $workspace): ZernioWorkspaceConnection
    {
        $this->ensureWebhook();
        $existing = ZernioWorkspaceConnection::where('workspace_id', $workspace->id)->first();
        if ($existing) {
            return $existing;
        }

        $client = app(ZernioClient::class);
        $profileName = 'CREASY CRM #'.$workspace->id.' · '.Str::limit($workspace->name, 120, '');

        try {
            $result = $client->createProfile([
                'name' => $profileName,
                'description' => 'Perfil isolado da empresa '.$workspace->id.' no CREASY CRM.',
                'color' => '#7C3AED',
            ], hash('sha256', 'respondesk-zernio-profile-'.$workspace->id));
            $profile = data_get($result, 'profile', []);
        } catch (ZernioApiException $exception) {
            if ($exception->status !== 409) {
                throw $exception;
            }

            $profileId = data_get($exception->payload, 'details.existingProfileId');
            $profiles = $profileId ? [] : data_get($client->profiles(['name' => $profileName]), 'profiles', []);
            $profile = $profileId ? ['_id' => $profileId, 'name' => $profileName] : ($profiles[0] ?? []);
        }

        $profileId = (string) ($profile['_id'] ?? $profile['id'] ?? '');
        if ($profileId === '') {
            throw new RuntimeException('O serviço de conexão não devolveu o identificador da empresa.');
        }

        return ZernioWorkspaceConnection::create([
            'workspace_id' => $workspace->id,
            'profile_id' => $profileId,
            'profile_name' => (string) ($profile['name'] ?? $profileName),
            'status' => 'connected',
        ]);
    }

    public function ensureWebhook(): void
    {
        $client = app(ZernioClient::class);
        $url = route('webhooks.zernio');
        $existing = collect(data_get($client->webhookSettings(), 'webhooks', []))
            ->first(fn (array $webhook) => rtrim((string) ($webhook['url'] ?? ''), '/') === rtrim($url, '/'));
        if ($existing) {
            return;
        }

        $secret = DB::transaction(function (): string {
            $config = IntegrationConfig::where('provider', 'zernio')
                ->where('mode', 'live')
                ->lockForUpdate()
                ->firstOrFail();
            $credentials = $config->credentials ?? [];
            $secret = (string) ($credentials['webhook_secret'] ?? '');
            if ($secret === '') {
                $secret = Str::random(64);
                $credentials['webhook_secret'] = $secret;
                $config->credentials = $credentials;
                $config->save();
            }

            return $secret;
        });

        $client->createWebhook([
            'name' => 'CREASY CRM central',
            'url' => $url,
            'secret' => $secret,
            'events' => [
                'account.connected', 'account.disconnected', 'account.ads.initial_sync_completed',
                'message.received', 'message.sent', 'message.edited', 'message.deleted',
                'message.delivered', 'message.read', 'message.failed', 'reaction.received',
                'conversation.started', 'comment.received', 'review.new', 'review.updated',
                'post.scheduled', 'post.published', 'post.failed', 'post.partial',
                'post.cancelled', 'post.recycled', 'post.external.created',
                'post.external.updated', 'post.external.deleted', 'lead.received',
                'ad.status_changed', 'whatsapp.template.status_updated',
            ],
            'isActive' => true,
        ]);
    }

    public function syncAccounts(ZernioWorkspaceConnection $connection): int
    {
        $client = app(ZernioClient::class);
        // Ads-only connections are returned by /accounts with platform values
        // such as metaads and googleads. /ads/accounts requires a social
        // accountId and cannot be queried by profile during workspace sync.
        $accounts = data_get($client->accounts($connection->profile_id), 'accounts', []);
        $workspace = Workspace::with('client')->find($connection->workspace_id);
        $limit = $workspace?->client?->effectivePlan()?->limitValue('unified_connections');
        $clientWorkspaceIds = $workspace?->client?->workspaces()->pluck('id')->all() ?? [$connection->workspace_id];
        $connectedElsewhere = ZernioAccountMapping::whereIn('workspace_id', $clientWorkspaceIds)
            ->where('connection_id', '!=', $connection->id)
            ->where('status', 'connected')
            ->count();
        $projectedCount = $connectedElsewhere + count($accounts);
        if ($limit !== null && $projectedCount > (int) $limit) {
            throw new RuntimeException(
                'A quantidade de contas conectadas excede o limite do plano ('.$projectedCount.'/'.(int) $limit.').'
            );
        }
        $seen = [];

        DB::transaction(function () use ($connection, $accounts, &$seen): void {
            foreach ($accounts as $account) {
                $accountId = (string) ($account['_id'] ?? $account['id'] ?? '');
                if ($accountId === '') {
                    continue;
                }

                $foreign = ZernioAccountMapping::where('account_id', $accountId)
                    ->where('workspace_id', '!=', $connection->workspace_id)
                    ->exists();
                if ($foreign) {
                    throw new RuntimeException('O serviço devolveu uma conta já vinculada a outra empresa. A sincronização foi bloqueada.');
                }

                $platform = $this->normalizedPlatform(
                    strtolower((string) ($account['platform'] ?? 'unknown')),
                    ($account['_respondesk_capability'] ?? null) === 'ads',
                );
                ZernioAccountMapping::updateOrCreate(
                    ['account_id' => $accountId],
                    [
                        'workspace_id' => $connection->workspace_id,
                        'connection_id' => $connection->id,
                        'profile_id' => $connection->profile_id,
                        'platform' => $platform,
                        'username' => $account['username'] ?? null,
                        'display_name' => $account['displayName'] ?? $account['name'] ?? null,
                        'status' => ($account['isActive'] ?? true) ? 'connected' : 'disconnected',
                        'capabilities' => $this->capabilities($platform),
                        'metadata' => $this->safeMetadata($account),
                    ],
                );
                $this->syncMessagingChannelAccount(
                    $connection,
                    $accountId,
                    $platform,
                    $account,
                );
                if ($this->capabilities($platform)['publishing']) {
                    SocialAccount::updateOrCreate(
                        [
                            'workspace_id' => $connection->workspace_id,
                            'network' => $platform,
                            'account_id' => $accountId,
                        ],
                        [
                            'name' => $account['displayName'] ?? $account['name'] ?? $account['username'] ?? ucfirst($platform),
                            'picture_url' => $account['pictureUrl'] ?? $account['avatarUrl'] ?? null,
                            'access_token' => 'zernio-managed',
                            'refresh_token' => null,
                            'token_expires_at' => null,
                            'scopes' => [],
                            'meta' => [
                                'provider' => 'zernio',
                                'zernio_profile_id' => $connection->profile_id,
                                'zernio_account_id' => $accountId,
                            ],
                            'active' => true,
                        ],
                    );
                }
                if (in_array($platform, ['metaads', 'googleads'], true)) {
                    AdConnection::updateOrCreate(
                        [
                            'workspace_id' => $connection->workspace_id,
                            'provider' => $platform === 'metaads' ? 'meta' : 'google',
                            'account_id' => $accountId,
                        ],
                        [
                            'name' => $account['displayName'] ?? $account['name'] ?? ucfirst($platform),
                            'credentials' => [
                                'provider' => 'zernio',
                                'zernio_profile_id' => $connection->profile_id,
                                'zernio_account_id' => $accountId,
                            ],
                            'status' => 'connected',
                            'last_error' => null,
                        ],
                    );
                }
                $seen[] = $accountId;
            }

            $query = ZernioAccountMapping::where('connection_id', $connection->id);
            if ($seen !== []) {
                $query->whereNotIn('account_id', $seen);
            }
            $query->update(['status' => 'disconnected']);

            SocialAccount::where('workspace_id', $connection->workspace_id)
                ->where('meta->provider', 'zernio')
                ->when($seen !== [], fn ($query) => $query->whereNotIn('account_id', $seen))
                ->update(['active' => false]);

            ChannelAccount::where('workspace_id', $connection->workspace_id)
                ->where('provider', 'zernio')
                ->when($seen !== [], fn ($query) => $query->whereNotIn('external_account_id', $seen))
                ->update(['status' => 'inactive']);

            $connection->update([
                'status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);
        });

        return count($seen);
    }

    public function connectionForWorkspace(int $workspaceId): ZernioWorkspaceConnection
    {
        return ZernioWorkspaceConnection::where('workspace_id', $workspaceId)->firstOrFail();
    }

    private function capabilities(string $platform): array
    {
        $messages = ['facebook', 'instagram', 'twitter', 'bluesky', 'reddit', 'telegram', 'whatsapp'];
        $ads = ['metaads', 'googleads', 'tiktokads', 'linkedinads', 'pinterestads', 'twitterads', 'openaiads'];

        return [
            'messages' => in_array($platform, $messages, true),
            'publishing' => ! in_array($platform, $ads, true) && $platform !== 'unknown',
            'ads' => in_array($platform, $ads, true),
            'analytics' => $platform !== 'unknown',
        ];
    }

    /**
     * A connected Facebook Page is also the Messenger account. Create the inbox
     * channel during account sync instead of waiting for the first live webhook.
     * Zernio's Facebook/Instagram history replay intentionally emits no webhooks.
     */
    private function syncMessagingChannelAccount(
        ZernioWorkspaceConnection $connection,
        string $accountId,
        string $platform,
        array $account,
    ): void {
        $channel = match ($platform) {
            'facebook' => 'messenger',
            'instagram', 'telegram', 'whatsapp' => $platform,
            default => null,
        };
        if ($channel === null) {
            return;
        }

        $channelAccount = ChannelAccount::firstOrNew([
            'workspace_id' => $connection->workspace_id,
            'channel' => $channel,
            'external_account_id' => $accountId,
        ]);
        $channelAccount->provider = 'zernio';
        $channelAccount->display_name = (string) (
            $account['displayName']
            ?? $account['name']
            ?? $account['username']
            ?? ucfirst($channel)
        );
        $channelAccount->status = ($account['isActive'] ?? true) ? 'active' : 'inactive';
        $channelAccount->credentials = [];
        $channelAccount->meta_json = array_merge($channelAccount->meta_json ?? [], [
            'zernio_profile_id' => $connection->profile_id,
            'zernio_account_id' => $accountId,
            'zernio_platform' => $platform,
            'managed_by' => 'zernio',
        ]);
        $channelAccount->save();
    }

    private function normalizedPlatform(string $platform, bool $ads): string
    {
        $platform = str_replace(['-', '_', ' '], '', $platform);
        if (! $ads) {
            return $platform;
        }

        return match ($platform) {
            'facebook', 'instagram', 'meta', 'metaads' => 'metaads',
            'google', 'googleads' => 'googleads',
            'linkedin', 'linkedinads' => 'linkedinads',
            'tiktok', 'tiktokads' => 'tiktokads',
            'twitter', 'x', 'twitterads', 'xads' => 'twitterads',
            'pinterest', 'pinterestads' => 'pinterestads',
            default => str_ends_with($platform, 'ads') ? $platform : $platform.'ads',
        };
    }

    private function safeMetadata(array $account): array
    {
        return collect($account)
            ->except(['accessToken', 'refreshToken', 'token', 'credentials', 'apiKey'])
            ->all();
    }
}
