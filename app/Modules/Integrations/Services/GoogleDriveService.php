<?php

namespace App\Modules\Integrations\Services;

use App\Modules\Integrations\Models\IntegrationConfig;
use App\Modules\Integrations\Models\WorkspaceIntegration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Smalot\PdfParser\Parser;

class GoogleDriveService
{
    private const AUTHORIZE_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const DRIVE_API = 'https://www.googleapis.com/drive/v3';

    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    public const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    private const SUPPORTED_MIME_TYPES = [
        'application/vnd.google-apps.document',
        'application/vnd.google-apps.spreadsheet',
        'application/vnd.google-apps.presentation',
        'application/pdf',
        'text/plain',
        'text/markdown',
        'text/csv',
        'text/html',
        'application/json',
    ];

    public function isConfigured(): bool
    {
        try {
            $this->applicationCredentials();

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $credentials = $this->applicationCredentials();

        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'scope' => implode(' ', [
                'openid',
                'email',
                'profile',
                'https://www.googleapis.com/auth/drive.readonly',
                self::CALENDAR_SCOPE,
            ]),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, mixed> */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): array
    {
        $credentials = $this->applicationCredentials();
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            throw new \RuntimeException('Não foi possível concluir a autorização do Google: '.($response->json('error_description') ?? $response->json('error') ?? $response->body()));
        }

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => (string) $response->json('refresh_token', ''),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String(),
            'scope' => (string) $response->json('scope', ''),
            'token_type' => (string) $response->json('token_type', 'Bearer'),
        ];
    }

    /** @return array<string, mixed> */
    public function userInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível identificar a conta Google conectada.');
        }

        return (array) $response->json();
    }

    public function hasCalendarScope(?WorkspaceIntegration $connection): bool
    {
        if (! $connection || $connection->status !== 'connected') {
            return false;
        }

        $credentials = $connection->credentials ?? [];
        $settings = $connection->settings ?? [];
        $scopes = (string) ($credentials['scope'] ?? $settings['scope'] ?? '');

        return in_array(self::CALENDAR_SCOPE, preg_split('/\s+/', trim($scopes)) ?: [], true);
    }

    /** @param array<string, mixed> $event */
    public function upsertCalendarEvent(WorkspaceIntegration $connection, string $calendarId, string $eventId, array $event): array
    {
        $baseUrl = self::CALENDAR_API.'/calendars/'.rawurlencode($calendarId).'/events';
        $request = Http::withToken($this->accessToken($connection))->timeout(30);
        $response = $request->put($baseUrl.'/'.rawurlencode($eventId).'?sendUpdates=all', $event);

        if ($response->status() === 404) {
            $response = Http::withToken($this->accessToken($connection))
                ->timeout(30)
                ->post($baseUrl.'?sendUpdates=all', $event + ['id' => $eventId]);
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            $this->recordConnectionError($connection, $message);
            throw new \RuntimeException('Não foi possível sincronizar o evento no Google Agenda: '.$message);
        }

        return (array) $response->json();
    }

    public function deleteCalendarEvent(WorkspaceIntegration $connection, string $calendarId, string $eventId): void
    {
        $response = Http::withToken($this->accessToken($connection))
            ->timeout(30)
            ->delete(self::CALENDAR_API.'/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId).'?sendUpdates=all');

        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $message = (string) ($response->json('error.message') ?? $response->body());
            $this->recordConnectionError($connection, $message);
            throw new \RuntimeException('Não foi possível remover o evento do Google Agenda: '.$message);
        }
    }

    /**
     * @return array{files: list<array<string, mixed>>, next_page_token: ?string}
     */
    public function listFiles(WorkspaceIntegration $connection, ?string $search = null, ?string $pageToken = null): array
    {
        $mimeQuery = collect(self::SUPPORTED_MIME_TYPES)
            ->map(fn (string $mime): string => "mimeType = '{$mime}'")
            ->implode(' or ');
        $query = "trashed = false and ({$mimeQuery})";
        if ($search !== null && trim($search) !== '') {
            $safeSearch = str_replace(['\\', "'"], ['\\\\', "\\'"], trim($search));
            $query .= " and name contains '{$safeSearch}'";
        }

        $params = [
            'q' => $query,
            'pageSize' => 100,
            'orderBy' => 'modifiedTime desc',
            'spaces' => 'drive',
            'corpora' => 'user',
            'includeItemsFromAllDrives' => 'true',
            'supportsAllDrives' => 'true',
            'fields' => 'nextPageToken,files(id,name,mimeType,modifiedTime,md5Checksum,headRevisionId,webViewLink,size,iconLink,parents)',
        ];
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }

        $response = Http::withToken($this->accessToken($connection))
            ->timeout(30)
            ->get(self::DRIVE_API.'/files', $params);

        if (! $response->successful()) {
            $this->recordConnectionError($connection, $response->json('error.message') ?? $response->body());
            throw new \RuntimeException('Não foi possível listar os arquivos do Google Drive: '.($response->json('error.message') ?? $response->body()));
        }

        return [
            'files' => array_values($response->json('files', [])),
            'next_page_token' => $response->json('nextPageToken'),
        ];
    }

    /** @return array<string, mixed> */
    public function metadata(WorkspaceIntegration $connection, string $fileId): array
    {
        $response = Http::withToken($this->accessToken($connection))
            ->timeout(20)
            ->get(self::DRIVE_API.'/files/'.rawurlencode($fileId), [
                'supportsAllDrives' => 'true',
                'fields' => 'id,name,mimeType,modifiedTime,md5Checksum,headRevisionId,webViewLink,size,iconLink,parents,trashed',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível consultar o arquivo no Google Drive: '.($response->json('error.message') ?? $response->body()));
        }

        $metadata = (array) $response->json();
        if (($metadata['trashed'] ?? false) === true) {
            throw new \RuntimeException('O arquivo foi movido para a lixeira no Google Drive.');
        }
        if (! in_array($metadata['mimeType'] ?? '', self::SUPPORTED_MIME_TYPES, true)) {
            throw new \RuntimeException('Este formato de arquivo ainda não é compatível com a Base de Conhecimento.');
        }

        return $metadata;
    }

    public function version(array $metadata): string
    {
        return (string) ($metadata['headRevisionId'] ?? $metadata['md5Checksum'] ?? $metadata['modifiedTime'] ?? '');
    }

    /**
     * Download/export a Drive file and return clean text suitable for indexing.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function extractText(WorkspaceIntegration $connection, string $fileId, ?array $metadata = null): string
    {
        $metadata ??= $this->metadata($connection, $fileId);
        $mime = (string) ($metadata['mimeType'] ?? '');
        $token = $this->accessToken($connection);

        if ($mime === 'application/vnd.google-apps.presentation') {
            return $this->extractPresentationText($token, $fileId);
        }

        $exportMime = match ($mime) {
            'application/vnd.google-apps.document' => 'text/plain',
            'application/vnd.google-apps.spreadsheet' => 'text/csv',
            default => null,
        };

        $request = Http::withToken($token)->timeout(60);
        $response = $exportMime
            ? $request->get(self::DRIVE_API.'/files/'.rawurlencode($fileId).'/export', ['mimeType' => $exportMime])
            : $request->get(self::DRIVE_API.'/files/'.rawurlencode($fileId), ['alt' => 'media', 'supportsAllDrives' => 'true']);

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível ler o arquivo do Google Drive: '.($response->json('error.message') ?? $response->body()));
        }

        $body = $response->body();
        if ($mime === 'application/pdf') {
            $tmp = tempnam(sys_get_temp_dir(), 'kbdrivepdf_');
            try {
                file_put_contents($tmp, $body);

                return (new Parser)->parseFile($tmp)->getText();
            } finally {
                @unlink($tmp);
            }
        }

        if ($mime === 'text/html') {
            return html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $body;
    }

    private function extractPresentationText(string $accessToken, string $fileId): string
    {
        $response = Http::withToken($accessToken)
            ->timeout(30)
            ->get('https://slides.googleapis.com/v1/presentations/'.rawurlencode($fileId));

        if (! $response->successful()) {
            throw new \RuntimeException('Não foi possível ler a apresentação do Google: '.($response->json('error.message') ?? $response->body()));
        }

        $parts = [];
        foreach ($response->json('slides', []) as $slide) {
            foreach ($slide['pageElements'] ?? [] as $element) {
                foreach ($element['shape']['text']['textElements'] ?? [] as $textElement) {
                    $content = trim((string) ($textElement['textRun']['content'] ?? ''));
                    if ($content !== '') {
                        $parts[] = $content;
                    }
                }
            }
        }

        return implode("\n", $parts);
    }

    public function accessToken(WorkspaceIntegration $connection): string
    {
        $credentials = $connection->credentials ?? [];
        $accessToken = (string) ($credentials['access_token'] ?? '');
        $expiresAt = $credentials['expires_at'] ?? null;

        if ($accessToken !== '' && $expiresAt && Carbon::parse($expiresAt)->isAfter(now()->addMinute())) {
            return $accessToken;
        }

        $refreshToken = (string) ($credentials['refresh_token'] ?? '');
        if ($refreshToken === '') {
            throw new \RuntimeException('A autorização do Google expirou. Reconecte o Google Drive.');
        }

        $application = $this->applicationCredentials();
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'client_id' => $application['client_id'],
            'client_secret' => $application['client_secret'],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! $response->json('access_token')) {
            $message = $response->json('error_description') ?? $response->json('error') ?? $response->body();
            $this->recordConnectionError($connection, $message);
            throw new \RuntimeException('Não foi possível renovar o acesso ao Google Drive. Reconecte a conta.');
        }

        $credentials['access_token'] = (string) $response->json('access_token');
        $credentials['expires_at'] = now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String();
        $connection->update([
            'credentials' => $credentials,
            'status' => 'connected',
            'last_error' => null,
            'last_tested_at' => now(),
        ]);

        return $credentials['access_token'];
    }

    /** @return array{client_id: string, client_secret: string} */
    private function applicationCredentials(): array
    {
        $config = IntegrationConfig::forProvider('google_workspace');
        $credentials = $config?->credentials ?? [];
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');

        if (! $config?->enabled || $clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('O aplicativo Google ainda não foi configurado pelo administrador do sistema.');
        }

        return ['client_id' => $clientId, 'client_secret' => $clientSecret];
    }

    private function recordConnectionError(WorkspaceIntegration $connection, string $message): void
    {
        $connection->update([
            'status' => 'error',
            'last_error' => mb_substr($message, 0, 2000),
            'last_tested_at' => now(),
        ]);
    }
}
