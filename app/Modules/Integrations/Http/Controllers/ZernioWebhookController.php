<?php

namespace App\Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Integrations\Jobs\ProcessZernioWebhookJob;
use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Integrations\Models\ZernioWorkspaceConnection;
use App\Services\WebhookIdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZernioWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookIdempotencyService $idempotency): JsonResponse
    {
        $config = IntegrationConfig::forProvider('zernio');
        $secret = (string) data_get($config?->credentials, 'webhook_secret', '');
        abort_if($secret === '', 503, 'Webhook Zernio não configurado.');

        $signature = trim((string) $request->header('X-Zernio-Signature', ''));
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Assinatura inválida.');

        $payload = $request->json()->all();
        $event = (string) ($payload['event'] ?? $payload['type'] ?? 'unknown');
        $eventId = (string) ($payload['id'] ?? $payload['eventId'] ?? $request->header('X-Zernio-Event-Id', ''));
        $eventId = $eventId !== '' ? $eventId : hash('sha256', $request->getContent());
        if (! $idempotency->isNewEvent('zernio', $eventId)) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }

        try {
            $profileId = (string) (data_get($payload, 'profileId')
                ?? data_get($payload, 'data.profileId')
                ?? data_get($payload, 'data.profile._id')
                ?? data_get($payload, 'account.profileId')
                ?? '');
            $accountId = (string) (data_get($payload, 'accountId')
                ?? data_get($payload, 'data.accountId')
                ?? data_get($payload, 'data.account._id')
                ?? data_get($payload, 'account.accountId')
                ?? data_get($payload, 'account.id')
                ?? '');

            $connection = $profileId !== ''
                ? ZernioWorkspaceConnection::where('profile_id', $profileId)->first()
                : null;

            if (! $connection && $accountId !== '') {
                $mapping = ZernioAccountMapping::where('account_id', $accountId)->first();
                $connection = $mapping ? ZernioWorkspaceConnection::find($mapping->connection_id) : null;
            }

            if (! $connection) {
                Log::warning('Webhook Zernio sem vínculo local; evento ignorado com segurança.', compact('event', 'eventId', 'profileId', 'accountId'));

                return response()->json(['received' => true, 'ignored' => true]);
            }

            ProcessZernioWebhookJob::dispatch($connection->id, $payload)->onQueue('default');

            return response()->json(['received' => true]);
        } catch (\Throwable $exception) {
            $idempotency->release('zernio', $eventId);
            Log::error('Falha ao processar webhook Zernio.', ['event' => $event, 'error' => $exception->getMessage()]);

            return response()->json(['received' => false], 500);
        }
    }
}
