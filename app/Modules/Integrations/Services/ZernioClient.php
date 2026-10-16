<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ZernioClient
{
    private IntegrationConfig $config;

    public function __construct()
    {
        $this->config = IntegrationConfig::forProvider('zernio')
            ?? throw new ZernioApiException(503, [], 'A conta central da Zernio ainda não foi configurada pelo administrador.');

        if (! $this->config->enabled || empty($this->config->credentials['api_key'])) {
            throw new ZernioApiException(503, [], 'A integração central da Zernio está desativada ou sem chave de API.');
        }
    }

    public function profiles(array $query = []): array
    {
        return $this->request('GET', 'profiles', $query);
    }

    public function createProfile(array $data, string $idempotencyKey): array
    {
        return $this->request('POST', 'profiles', $data, ['Idempotency-Key' => $idempotencyKey]);
    }

    public function accounts(string $profileId): array
    {
        return $this->request('GET', 'accounts', ['profileId' => $profileId]);
    }

    public function connectUrl(string $platform, string $profileId, string $redirectUrl, bool $ads = false): array
    {
        $path = 'connect/'.$platform.($ads ? '/ads' : '');

        return $this->request('GET', $path, [
            'profileId' => $profileId,
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function conversations(string $profileId, array $filters = []): array
    {
        return $this->request('GET', 'inbox/conversations', array_merge($filters, ['profileId' => $profileId]));
    }

    public function sendMessage(string $conversationId, array $data, string $idempotencyKey): array
    {
        return $this->request(
            'POST',
            'inbox/conversations/'.rawurlencode($conversationId).'/messages',
            $data,
            ['Idempotency-Key' => $idempotencyKey],
        );
    }

    public function attachmentUrl(
        string $conversationId,
        string $messageId,
        int $index,
        string $accountId,
    ): array {
        return $this->request(
            'GET',
            'inbox/conversations/'.rawurlencode($conversationId)
                .'/messages/'.rawurlencode($messageId)
                .'/attachments/'.$index,
            ['accountId' => $accountId, 'format' => 'json'],
        );
    }

    public function posts(string $profileId, array $filters = []): array
    {
        return $this->request('GET', 'posts', array_merge($filters, ['profileId' => $profileId]));
    }

    public function createPost(array $data, string $idempotencyKey): array
    {
        return $this->request('POST', 'posts', $data, ['Idempotency-Key' => $idempotencyKey]);
    }

    public function ads(string $profileId, array $filters = []): array
    {
        return $this->request('GET', 'ads', array_merge($filters, ['profileId' => $profileId]));
    }

    public function adTree(string $profileId, array $filters = []): array
    {
        return $this->request('GET', 'ads/tree', array_merge($filters, ['profileId' => $profileId]));
    }

    public function webhookSettings(): array
    {
        return $this->request('GET', 'webhooks/settings');
    }

    public function createWebhook(array $data): array
    {
        return $this->request('POST', 'webhooks/settings', $data);
    }

    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $response = $this->http()->withHeaders($headers)->send($method, $path, [
            $method === 'GET' ? 'query' : 'json' => $data,
        ]);

        if (! $response->successful()) {
            $payload = $response->json() ?: [];
            $message = (string) data_get(
                $payload,
                'message',
                data_get($payload, 'error.message', data_get($payload, 'error', 'Falha na API Zernio (HTTP '.$response->status().').')),
            );
            throw new ZernioApiException($response->status(), $payload, $message);
        }

        return $response->json() ?: [];
    }

    private function http(): PendingRequest
    {
        $credentials = $this->config->credentials ?? [];

        return Http::baseUrl(rtrim((string) ($credentials['base_url'] ?? 'https://zernio.com/api/v1'), '/').'/')
            ->acceptJson()
            ->withToken((string) $credentials['api_key'])
            ->timeout(30)
            ->retry(2, 300, throw: false);
    }
}
