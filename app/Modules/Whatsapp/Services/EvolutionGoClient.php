<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Support\PublicHttpUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EvolutionGoClient
{
    public const WEBHOOK_EVENTS = [
        'MESSAGE',
        'SEND_MESSAGE',
        'PRESENCE',
        'CHAT_PRESENCE',
        'CONNECTION',
        'READ_RECEIPT',
        'HISTORY_SYNC',
        'CALL',
        'QRCODE',
    ];

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
        PublicHttpUrl::inspect($this->baseUrl);
    }

    public static function system(): ?self
    {
        $config = IntegrationConfig::forProvider('evolution_go');
        $credentials = $config?->credentials ?? [];

        if (! $config?->enabled || empty($credentials['base_url']) || empty($credentials['global_api_key'])) {
            return null;
        }

        return new self($credentials['base_url'], $credentials['global_api_key']);
    }

    public static function forAccount(ChannelAccount $account): self
    {
        $system = IntegrationConfig::forProvider('evolution_go');
        $baseUrl = (string) (($system?->credentials ?? [])['base_url'] ?? '');
        $token = (string) (($account->credentials ?? [])['instance_token'] ?? '');

        if ($baseUrl === '' || $token === '') {
            throw new \RuntimeException('A configuração interna da conexão de WhatsApp está incompleta.');
        }

        return new self($baseUrl, $token);
    }

    /** @return array<string, mixed> */
    public function listInstances(): array
    {
        $url = $this->url('/instance/all');

        return $this->json($this->request($url)->get($url));
    }

    /** @return array<string, mixed> */
    public function createInstance(string $name, string $instanceToken): array
    {
        $url = $this->url('/instance/create');

        return $this->json($this->request($url)->post($url, [
            'name' => $name,
            'token' => $instanceToken,
        ]));
    }

    /** @return array<string, mixed> */
    public function connect(string $webhookUrl, array $options = []): array
    {
        $url = $this->url('/instance/connect');
        $subscriptions = array_values(array_intersect(
            self::WEBHOOK_EVENTS,
            is_array($options['subscribe'] ?? null)
                ? $options['subscribe']
                : ['MESSAGE', 'READ_RECEIPT', 'CONNECTION', 'QRCODE'],
        ));
        if ($subscriptions === []) {
            $subscriptions = ['MESSAGE'];
        }

        return $this->json($this->request($url)->post($url, [
            'webhookUrl' => $webhookUrl,
            'subscribe' => $subscriptions,
            'immediate' => true,
            'rabbitmqEnable' => $this->transportOption($options['rabbitmq'] ?? null),
            'websocketEnable' => $this->transportOption($options['websocket'] ?? null),
            'natsEnable' => $this->transportOption($options['nats'] ?? null),
        ]));
    }

    /** @param array<string, mixed> $settings */
    public function updateAdvancedSettings(string $instanceId, array $settings): array
    {
        $url = $this->url('/instance/'.rawurlencode($instanceId).'/advanced-settings');

        return $this->json($this->request($url)->put($url, [
            'alwaysOnline' => (bool) ($settings['always_online'] ?? false),
            'rejectCall' => (bool) ($settings['reject_call'] ?? false),
            'msgRejectCall' => (string) ($settings['reject_call_message'] ?? ''),
            'readMessages' => (bool) ($settings['read_messages'] ?? false),
            'ignoreGroups' => true,
            'ignoreStatus' => (bool) ($settings['ignore_status'] ?? false),
        ]));
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $url = $this->url('/instance/status');

        return $this->json($this->request($url)->get($url));
    }

    /** @return array<string, mixed> */
    public function qrCode(): array
    {
        $url = $this->url('/instance/qr');

        return $this->json($this->request($url)->get($url));
    }

    /**
     * Return the address-book contacts exposed by the connected WhatsApp account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listContacts(): array
    {
        $url = $this->url('/user/contacts');

        try {
            $json = $this->json($this->request($url)->get($url));
        } catch (ConnectionException $exception) {
            throw EvolutionGoConnectionException::timeout($exception);
        }

        $contacts = $json['data'] ?? [];

        return is_array($contacts) ? array_values(array_filter($contacts, 'is_array')) : [];
    }

    /**
     * Return the groups, including participant data exposed by Evolution Go.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listGroups(): array
    {
        $url = $this->url('/group/list');

        try {
            $json = $this->json($this->request($url)->get($url));
        } catch (ConnectionException $exception) {
            throw EvolutionGoConnectionException::timeout($exception);
        }

        $groups = $json['data'] ?? $json['groups'] ?? [];

        return is_array($groups) ? array_values(array_filter($groups, 'is_array')) : [];
    }

    public function sendText(string $number, string $text, bool $formatJid = true): string
    {
        $url = $this->url('/send/text');
        try {
            $json = $this->json($this->outboundRequest($url)->post($url, [
                'number' => $this->recipient($number, $formatJid),
                'text' => $text,
                'formatJid' => $formatJid,
            ]));
        } catch (ConnectionException $exception) {
            throw EvolutionGoConnectionException::timeout($exception);
        }

        return $this->messageId($json);
    }

    public function sendMedia(string $number, string $type, string $url, ?string $caption, ?string $filename, bool $formatJid = true): string
    {
        $payload = [
            'number' => $this->recipient($number, $formatJid),
            'url' => $url,
            'type' => $type,
            'caption' => $caption ?? '',
            'formatJid' => $formatJid,
        ];
        if ($filename) {
            $payload['filename'] = $filename;
        }

        $url = $this->url('/send/media');

        try {
            return $this->messageId($this->json($this->outboundRequest($url)->post($url, $payload)));
        } catch (ConnectionException $exception) {
            throw EvolutionGoConnectionException::timeout($exception);
        }
    }

    /**
     * Download and decrypt media from a webhook's original WhatsApp Message
     * object. Evolution Go returns a data URL containing the binary payload.
     *
     * @param  array<string, mixed>  $message
     * @return array{bytes:string,mime_type:string}
     */
    public function downloadMedia(array $message): array
    {
        $url = $this->url('/message/downloadmedia');
        $response = Http::acceptJson()
            ->asJson()
            ->timeout(90)
            ->connectTimeout(8)
            ->retry(1, 250, throw: false)
            ->withOptions(PublicHttpUrl::requestOptions($url))
            ->withHeaders(['apikey' => $this->apiKey])
            ->post($url, ['message' => $message]);
        $json = $this->json($response);
        $dataUrl = data_get($json, 'data.base64');

        if (! is_string($dataUrl)
            || ! preg_match('/^data:([^;,]+);base64,(.+)$/s', $dataUrl, $matches)) {
            throw new \RuntimeException('O gateway não retornou a mídia em um formato válido.');
        }
        if (strlen($matches[2]) > 40 * 1024 * 1024) {
            throw new \RuntimeException('A mídia excede o limite de 30 MB.');
        }

        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Não foi possível decodificar a mídia recebida.');
        }

        return ['bytes' => $bytes, 'mime_type' => strtolower($matches[1])];
    }

    public function disconnect(): void
    {
        $url = $this->url('/instance/disconnect');
        $this->json($this->request($url)->post($url, []));
    }

    public function logout(): void
    {
        $url = $this->url('/instance/logout');
        $this->json($this->request($url)->delete($url, []));
    }

    public function deleteInstance(string $instanceId): void
    {
        $url = $this->url('/instance/delete/'.rawurlencode($instanceId));
        $this->json($this->request($url)->delete($url));
    }

    private function request(string $url): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(8)
            ->retry(2, 250, throw: false)
            ->withOptions(PublicHttpUrl::requestOptions($url))
            ->withHeaders(['apikey' => $this->apiKey]);
    }

    private function outboundRequest(string $url): PendingRequest
    {
        // Never retry a send automatically: the gateway may have accepted the
        // message before losing the response, which could duplicate delivery.
        return Http::acceptJson()
            ->asJson()
            ->timeout(10)
            ->connectTimeout(5)
            ->withOptions(PublicHttpUrl::requestOptions($url))
            ->withHeaders(['apikey' => $this->apiKey]);
    }

    private function url(string $path): string
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
        PublicHttpUrl::inspect($url);

        return $url;
    }

    private function transportOption(mixed $value): string
    {
        return $value === 'enabled' ? 'enabled' : 'disabled';
    }

    private function recipient(string $number, bool $formatJid): string
    {
        $number = trim($number);
        if ($formatJid) {
            $digits = preg_replace('/\D+/', '', $number) ?: '';
            if ($digits === '') {
                throw new \RuntimeException('O destinatário do WhatsApp não possui um número válido.');
            }

            return $digits;
        }

        if (! preg_match('/^[0-9]+(?:@[a-z0-9.-]+)?$/i', $number)) {
            throw new \RuntimeException('O identificador do destinatário do WhatsApp é inválido.');
        }

        return $number;
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        if (! $response->successful()) {
            if ($response->json('code') === 'LICENSE_REQUIRED') {
                throw new \RuntimeException('O gateway de WhatsApp não oficial ainda não foi ativado. Abra o gerenciador do gateway e conclua a ativação gratuita.');
            }
            $error = $response->json('error') ?: $response->json('message');
            if (is_array($error) || is_object($error)) {
                $error = json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $normalisedError = Str::lower((string) $error);
            if (str_contains($normalisedError, 'record not found')
                || str_contains($normalisedError, 'instance not found')
                || str_contains($normalisedError, 'not connected')
                || str_contains($normalisedError, 'disconnected')
                || str_contains($normalisedError, 'logged out')) {
                throw EvolutionGoConnectionException::disconnected();
            }
            throw new \RuntimeException(
                (string) ($error ?: "O gateway de WhatsApp respondeu com HTTP {$response->status()}.")
            );
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    /** @param array<string, mixed> $json */
    private function messageId(array $json): string
    {
        $id = data_get($json, 'data.messageId')
            ?? data_get($json, 'data.key.id')
            ?? data_get($json, 'data.Info.ID')
            ?? data_get($json, 'data.info.id')
            ?? data_get($json, 'messageId')
            ?? data_get($json, 'key.id');

        if (! is_scalar($id) || (string) $id === '') {
            throw new \RuntimeException('O serviço de WhatsApp não retornou o identificador da mensagem.');
        }

        return (string) $id;
    }
}
