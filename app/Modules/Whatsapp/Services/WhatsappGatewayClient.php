<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Shared\Models\ChannelAccount;
use App\Support\PublicHttpUrl;
use Illuminate\Support\Facades\Http;

/** WAHA HTTP API and Evolution API v2 (separate from Evolution Go). */
class WhatsappGatewayClient
{
    public const PROVIDERS = ['waha', 'evolution_api'];

    public function __construct(
        private readonly string $provider,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $instance = '',
    ) {
        if (! in_array($provider, self::PROVIDERS, true) || $baseUrl === '' || $apiKey === '') {
            throw new \RuntimeException('Configure a URL e a chave da integração de WhatsApp.');
        }
    }

    public static function forAccount(ChannelAccount $account): self
    {
        $config = IntegrationConfig::forProvider($account->provider);
        if (! $config?->enabled) {
            throw new \RuntimeException('A integração de WhatsApp está desativada.');
        }
        $credentials = $account->credentials ?? [];

        // Apply administrative key rotation to connections on the same server.
        $key = rtrim($config->credentials['base_url'] ?? '', '/') === rtrim($credentials['base_url'] ?? '', '/')
            ? ($config->credentials['api_key'] ?? '') : ($credentials['api_key'] ?? '');

        return new self($account->provider, $credentials['base_url'] ?? '', $key, $credentials['instance'] ?? '');
    }

    public function request(string $method, string $path, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/').$path;
        $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(30)
            ->withOptions(PublicHttpUrl::requestOptions($url))
            ->withHeaders([$this->provider === 'waha' ? 'X-Api-Key' : 'apikey' => $this->apiKey])
            ->send($method, $url, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        // Never expose provider response bodies: these may contain credentials.
        if ($method === 'DELETE' && $response->status() === 404) {
            return []; // A failed creation can be removed even if no remote session exists.
        }
        if (! $response->successful()) {
            throw new \RuntimeException('Falha no gateway WhatsApp (HTTP '.$response->status().').');
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    /** Download on the server: API credentials must never reach browser media URLs. */
    public function downloadMedia(array $media, bool $refreshed = false): array
    {
        if ($this->provider === 'evolution_api') {
            $data = $this->request('POST', '/chat/getBase64FromMediaMessage/'.rawurlencode($this->instance), [
                'message' => ['key' => ['id' => $media['id'] ?? '']], 'convertToMp4' => false,
            ]);
            $encoded = $data['base64'] ?? '';
            if (! is_string($encoded) || strlen($encoded) > 40 * 1024 * 1024) {
                throw new \RuntimeException('Mídia inválida ou maior que 30 MB.');
            }
            $encoded = preg_replace('#^data:[^;,]+;base64,#', '', $encoded);
            $bytes = base64_decode($encoded, true);
        } else {
            $source = $media['url'] ?? null;
            if (! $source && ! empty($media['chat_id']) && ! empty($media['id'])) {
                $message = $this->request('GET', '/api/'.rawurlencode($this->instance).'/chats/'
                    .rawurlencode($media['chat_id']).'/messages/'.rawurlencode($media['id']), ['downloadMedia' => 'true']);
                $source = data_get($message, 'media.url');
            }
            // WAHA may advertise a Docker localhost hostname. Only use its file path
            // on the configured gateway, never an arbitrary authenticated URL.
            $path = is_string($source) ? parse_url($source, PHP_URL_PATH) : null;
            if (! is_string($path) || ! str_starts_with($path, '/api/files/')
                || preg_match('#(?:\\\\|\.\.|%2e|%2f|%5c)#i', $path)) {
                throw new \RuntimeException('O gateway ainda não disponibilizou o arquivo de mídia.');
            }
            $url = rtrim($this->baseUrl, '/').$path;
            $response = Http::connectTimeout(5)->timeout(60)
                ->withOptions(array_merge(PublicHttpUrl::requestOptions($url), [
                    'progress' => static function ($total, $downloaded): void {
                        if (max($total, $downloaded) > 30 * 1024 * 1024) {
                            throw new \RuntimeException('A mídia excede 30 MB.');
                        }
                    },
                ]))->withHeaders(['X-Api-Key' => $this->apiKey])->get($url);
            if ($response->status() === 404 && !$refreshed && !empty($media['chat_id']) && !empty($media['id'])) {
                // Ask WAHA to download again when its temporary file has expired.
                return $this->downloadMedia(array_merge($media, ['url' => null]), true);
            }
            if (! $response->successful()) {
                throw new \RuntimeException('Não foi possível baixar a mídia (HTTP '.$response->status().').');
            }
            $bytes = $response->body();
        }
        if (! is_string($bytes) || $bytes === '' || strlen($bytes) > 30 * 1024 * 1024) {
            throw new \RuntimeException('Arquivo de mídia vazio ou inválido.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';
        // Never render active documents as HTML/SVG on the authenticated app origin.
        if (in_array($mime, ['text/html', 'image/svg+xml', 'application/xhtml+xml'], true)) {
            $mime = 'application/octet-stream';
        }
        return ['bytes' => $bytes, 'mime_type' => $mime];
    }

    public function test(): array
    {
        $this->request('GET', $this->provider === 'waha' ? '/api/sessions' : '/instance/fetchInstances');

        return ['ok' => true, 'message' => 'Gateway WhatsApp conectado.'];
    }

    public function create(string $webhookUrl): void
    {
        if ($this->provider === 'waha') {
            $this->request('POST', '/api/sessions', [
                'name' => $this->instance, 'start' => true,
                'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message.any', 'message.reaction', 'message.ack', 'session.status']]]],
            ]);
        } else {
            $this->request('POST', '/instance/create', [
                'instanceName' => $this->instance, 'integration' => 'WHATSAPP-BAILEYS', 'qrcode' => true,
                'webhook' => ['url' => $webhookUrl, 'enabled' => true, 'byEvents' => false, 'base64' => false,
                    'events' => ['MESSAGES_UPSERT', 'MESSAGES_UPDATE', 'CONNECTION_UPDATE']],
            ]);
        }
    }

    public function status(): string
    {
        $name = rawurlencode($this->instance);
        $data = $this->request('GET', $this->provider === 'waha' ? '/api/sessions/'.$name : '/instance/connectionState/'.$name);

        return (string) ($this->provider === 'waha' ? ($data['status'] ?? 'UNKNOWN') : data_get($data, 'instance.state', 'unknown'));
    }

    public function qr(): ?string
    {
        $name = rawurlencode($this->instance);
        $data = $this->request('GET', $this->provider === 'waha' ? '/api/'.$name.'/auth/qr' : '/instance/connect/'.$name);
        $value = $this->provider === 'waha' ? ($data['data'] ?? null) : ($data['base64'] ?? null);
        if (! is_string($value) || $value === '') {
            return null;
        }
        $value = preg_replace('#^data:image/(png|jpeg);base64,#', '', $value);
        $bytes = base64_decode($value, true);
        if ($bytes === false || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return null;
        }

        return 'data:image/png;base64,'.$value;
    }

    public function restart(): void
    {
        $name = rawurlencode($this->instance);
        $this->request($this->provider === 'waha' ? 'POST' : 'PUT', $this->provider === 'waha' ? '/api/sessions/'.$name.'/restart' : '/instance/restart/'.$name);
    }

    public function delete(): void
    {
        $name = rawurlencode($this->instance);
        $this->request('DELETE', $this->provider === 'waha' ? '/api/sessions/'.$name : '/instance/delete/'.$name);
    }

    public function phoneNumberForLid(string $lid): ?string
    {
        $response = $this->request('GET', '/api/'.rawurlencode($this->instance).'/lids/'.rawurlencode($lid));
        $pn = $response['pn'] ?? null;
        return is_string($pn) ? $pn : null;
    }

    public function sendImage(string $recipient, string $url, string $caption, string $filename, string $mime): string
    {
        if ($this->provider !== 'waha') {
            throw new \RuntimeException('Envio de imagem indisponível neste gateway.');
        }
        $lid = preg_match('/^[1-9][0-9]{5,19}@lid$/', $recipient);
        $number = $lid ? '' : preg_replace('/\D/', '', $recipient);
        if (! $lid && ! preg_match('/^[1-9][0-9]{6,14}$/', $number)) {
            throw new \RuntimeException('Informe um telefone internacional válido.');
        }
        if (str_starts_with($url, '/')) {
            $url = url($url);
        }
        PublicHttpUrl::inspect($url);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Envie uma imagem JPEG, PNG ou WebP.');
        }
        $result = $this->request('POST', '/api/sendImage', [
            'session' => $this->instance, 'chatId' => $lid ? $recipient : $number.'@c.us',
            'file' => ['url' => $url, 'filename' => basename($filename), 'mimetype' => $mime],
            'caption' => $caption,
        ]);
        $id = self::wahaMessageId($result);
        if (! $id) {
            throw new \RuntimeException('O gateway não confirmou o identificador da imagem. Verifique o envio antes de repetir.');
        }
        return $id;
    }

    public function sendText(string $phone, string $text): string
    {
        $lid = $this->provider === 'waha' && preg_match('/^[1-9][0-9]{5,19}@lid$/', $phone);
        $number = $lid ? '' : preg_replace('/\D/', '', $phone);
        if ((! $lid && ! preg_match('/^[1-9][0-9]{6,14}$/', $number)) || trim($text) === '') {
            throw new \RuntimeException('Informe um telefone internacional e uma mensagem válida.');
        }
        $data = $this->provider === 'waha'
            ? $this->request('POST', '/api/sendText', ['session' => $this->instance, 'chatId' => $lid ? $phone : $number.'@c.us', 'text' => $text])
            : $this->request('POST', '/message/sendText/'.rawurlencode($this->instance), ['number' => $number, 'text' => $text]);
        $id = $this->provider === 'waha'
            ? self::wahaMessageId($data)
            : data_get($data, 'key.id');
        if (! is_string($id) || $id === '') {
            throw new \RuntimeException('O gateway não confirmou o identificador da mensagem. Verifique o envio antes de repetir.');
        }

        return $id;
    }

    /** NOWEB uses the remote chat in `from` for both directions and may omit `to`. */
    public static function wahaChatId(array $data): ?string
    {
        $fromMe = filter_var($data['fromMe'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $id = self::wahaMessageId($data);
        $serializedChat = is_string($id) && preg_match('/^(?:true|false)_([^_]+)_/', $id, $match) ? $match[1] : null;
        $candidates = $fromMe
            ? [data_get($data, '_data.key.remoteJid'), $data['to'] ?? null, $serializedChat, $data['from'] ?? null]
            : [$data['from'] ?? null, data_get($data, '_data.key.remoteJid'), $serializedChat];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                // Preserve group IDs so callers can reject them rather than select a participant.
                return $candidate;
            }
        }
        return null;
    }

    /** WAHA exposes different response shapes for NOWEB and browser engines. */
    public static function wahaMessageId(array $data): ?string
    {
        foreach ([$data['id'] ?? null, data_get($data, 'id._serialized'), data_get($data, 'key.id'), data_get($data, 'id.id')] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }
        return null;
    }

    public static function send(ChannelAccount $account, string $phone, string $body): string
    {
        if ($account->status !== 'active') {
            throw new \RuntimeException('A conta de WhatsApp não está conectada.');
        }

        return $account->provider.':'.$account->id.':'.self::forAccount($account)->sendText($phone, $body);
    }
}
