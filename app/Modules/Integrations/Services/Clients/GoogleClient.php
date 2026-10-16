<?php

namespace App\Modules\Integrations\Services\Clients;

use App\Modules\Integrations\Models\IntegrationConfig;
use Illuminate\Support\Facades\Http;

/**
 * Thin REST client for Google Workspace APIs (Sheets, Docs, Drive, Calendar/Meet).
 *
 * Credentials are resolved from the `google_workspace` IntegrationConfig provider,
 * which stores an OAuth client_id / client_secret and a long-lived refresh_token.
 * No interactive OAuth login flow is built — an admin pastes a refresh token
 * (obtained once via the Google OAuth Playground or an offline-access grant) and
 * this client exchanges it for short-lived access tokens at runtime.
 *
 * Every method throws a RuntimeException with a clear, surfaceable message so the
 * automation engine can record it on the run log.
 */
class GoogleClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private ?string $accessToken = null;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {}

    /**
     * Build a client from the configured `google_workspace` integration, or null
     * when it has not been configured/enabled. Handlers should treat null as
     * "integration not connected".
     */
    public static function resolve(): ?self
    {
        $config = IntegrationConfig::forProvider('google_workspace');
        if (! $config || ! $config->enabled) {
            return null;
        }

        $creds = $config->credentials ?? [];
        $clientId = (string) ($creds['client_id'] ?? '');
        $clientSecret = (string) ($creds['client_secret'] ?? '');
        $refreshToken = (string) ($creds['refresh_token'] ?? '');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return null;
        }

        return new self($clientId, $clientSecret, $refreshToken);
    }

    /** Exchange the refresh token for a short-lived access token (memoised per instance). */
    public function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $resp = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new \RuntimeException('Falha na autenticação do Google: '.($resp->json('error_description') ?? $resp->json('error') ?? $resp->body()));
        }

        return $this->accessToken = (string) $resp->json('access_token');
    }

    // ─── Sheets ──────────────────────────────────────────────────────────────

    /**
     * Append a single row of values to a spreadsheet range (e.g. "Sheet1!A:Z").
     *
     * @param  list<string>  $values
     */
    public function appendSheetRow(string $spreadsheetId, string $range, array $values): array
    {
        $resp = Http::withToken($this->accessToken())
            ->timeout(20)
            ->post("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/".rawurlencode($range).':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS', [
                'values' => [array_values($values)],
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('Não foi possível adicionar a linha no Google Planilhas: '.($resp->json('error.message') ?? $resp->body()));
        }

        return [
            'updated_range' => $resp->json('updates.updatedRange'),
            'updated_cells' => $resp->json('updates.updatedCells', 0),
        ];
    }

    /** Read a range and return the raw 2D value array. */
    public function readSheetRange(string $spreadsheetId, string $range): array
    {
        $resp = Http::withToken($this->accessToken())
            ->timeout(20)
            ->get("https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/".rawurlencode($range));

        if (! $resp->successful()) {
            throw new \RuntimeException('Não foi possível ler o intervalo no Google Planilhas: '.($resp->json('error.message') ?? $resp->body()));
        }

        return $resp->json('values', []);
    }

    // ─── Docs (via Drive copy + Docs replace) ─────────────────────────────────

    /**
     * Copy a template Doc, run find/replace on {{placeholder}} tokens, and return
     * the new document id + share URL.
     *
     * @param  array<string, string>  $replacements  map of placeholder => value (placeholders WITHOUT braces)
     */
    public function createDocFromTemplate(string $templateDocId, string $title, array $replacements = []): array
    {
        $token = $this->accessToken();

        $copy = Http::withToken($token)
            ->timeout(20)
            ->post("https://www.googleapis.com/drive/v3/files/{$templateDocId}/copy", ['name' => $title]);

        if (! $copy->successful()) {
            throw new \RuntimeException('Não foi possível copiar o documento no Google Docs: '.($copy->json('error.message') ?? $copy->body()));
        }

        $docId = (string) $copy->json('id');

        if (! empty($replacements)) {
            $requests = [];
            foreach ($replacements as $key => $value) {
                $requests[] = [
                    'replaceAllText' => [
                        'containsText' => ['text' => '{{'.$key.'}}', 'matchCase' => false],
                        'replaceText' => (string) $value,
                    ],
                ];
            }

            $update = Http::withToken($token)
                ->timeout(20)
                ->post("https://docs.googleapis.com/v1/documents/{$docId}:batchUpdate", ['requests' => $requests]);

            if (! $update->successful()) {
                throw new \RuntimeException('Não foi possível preencher o documento no Google Docs: '.($update->json('error.message') ?? $update->body()));
            }
        }

        return [
            'doc_id' => $docId,
            'url' => "https://docs.google.com/document/d/{$docId}/edit",
        ];
    }

    // ─── Forms ─────────────────────────────────────────────────────────────────

    /** Fetch a form's metadata (title, responderUri, items). */
    public function getForm(string $formId): array
    {
        $resp = Http::withToken($this->accessToken())
            ->timeout(20)
            ->get("https://forms.googleapis.com/v1/forms/{$formId}");

        if (! $resp->successful()) {
            throw new \RuntimeException('Não foi possível consultar o Google Formulários: '.($resp->json('error.message') ?? $resp->body()));
        }

        return $resp->json();
    }

    /**
     * List a form's submitted responses (most recent first).
     *
     * @return list<array<string, mixed>>
     */
    public function listFormResponses(string $formId): array
    {
        $resp = Http::withToken($this->accessToken())
            ->timeout(20)
            ->get("https://forms.googleapis.com/v1/forms/{$formId}/responses");

        if (! $resp->successful()) {
            throw new \RuntimeException('Não foi possível consultar as respostas do Google Formulários: '.($resp->json('error.message') ?? $resp->body()));
        }

        $responses = $resp->json('responses', []);
        usort($responses, fn ($a, $b) => strcmp(
            $b['lastSubmittedTime'] ?? $b['createTime'] ?? '',
            $a['lastSubmittedTime'] ?? $a['createTime'] ?? '',
        ));

        return $responses;
    }

    // ─── Calendar / Meet ──────────────────────────────────────────────────────

    /**
     * Create a calendar event. When $withMeet is true a Google Meet conference is
     * attached and the join URL is returned in `meet_url`.
     *
     * @param  list<string>  $attendeeEmails
     */
    public function createCalendarEvent(
        string $calendarId,
        string $summary,
        string $startIso,
        string $endIso,
        array $attendeeEmails = [],
        bool $withMeet = false,
        ?string $description = null,
        ?string $timezone = null,
    ): array {
        $event = [
            'summary' => $summary,
            'start' => ['dateTime' => $startIso] + ($timezone ? ['timeZone' => $timezone] : []),
            'end' => ['dateTime' => $endIso] + ($timezone ? ['timeZone' => $timezone] : []),
        ];

        if ($description) {
            $event['description'] = $description;
        }

        if (! empty($attendeeEmails)) {
            $event['attendees'] = array_map(fn ($e) => ['email' => $e], array_filter($attendeeEmails));
        }

        $query = '?sendUpdates=all';
        if ($withMeet) {
            $event['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'meet-'.substr(md5($summary.$startIso), 0, 16),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
            $query .= '&conferenceDataVersion=1';
        }

        $resp = Http::withToken($this->accessToken())
            ->timeout(20)
            ->post('https://www.googleapis.com/calendar/v3/calendars/'.rawurlencode($calendarId).'/events'.$query, $event);

        if (! $resp->successful()) {
            throw new \RuntimeException('Não foi possível criar o evento no Google Agenda: '.($resp->json('error.message') ?? $resp->body()));
        }

        $meetUrl = $resp->json('hangoutLink');
        if (! $meetUrl) {
            foreach ($resp->json('conferenceData.entryPoints', []) as $entry) {
                if (($entry['entryPointType'] ?? '') === 'video') {
                    $meetUrl = $entry['uri'] ?? null;
                    break;
                }
            }
        }

        return [
            'event_id' => $resp->json('id'),
            'html_link' => $resp->json('htmlLink'),
            'meet_url' => $meetUrl,
        ];
    }
}
