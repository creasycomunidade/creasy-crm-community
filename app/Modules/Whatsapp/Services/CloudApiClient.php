<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappPhoneNumber;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudApiClient
{
    private const BASE = 'https://graph.facebook.com/v20.0';

    public function __construct(
        private readonly string $phoneNumberId,
        private readonly string $accessToken,
    ) {}

    public static function forWorkspace(int $workspaceId): ?static
    {
        $waba = WhatsappBusinessAccount::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->with('phoneNumbers')
            ->first();

        if (! $waba) {
            Log::warning('CloudApiClient: no active WABA for workspace', ['workspace_id' => $workspaceId]);

            return null;
        }

        $token = $waba->accessToken() ?? WhatsappBusinessAccount::resolveAccessTokenForWorkspace($workspaceId);
        $phoneNumberId = WhatsappBusinessAccount::defaultPhoneNumberIdForWorkspace($workspaceId) ?? '';

        if (! $token || $phoneNumberId === '') {
            Log::warning('CloudApiClient: missing credentials', [
                'workspace_id' => $workspaceId,
                'waba_id' => $waba->waba_id,
                'token_empty' => empty($token),
                'phone_number_id' => $phoneNumberId ?: 'EMPTY',
                'phone_count' => $waba->phoneNumbers->count(),
            ]);

            return null;
        }

        return new static($phoneNumberId, $token);
    }

    public static function forPhoneNumber(string $phoneNumberId, int $workspaceId): ?static
    {
        $phone = WhatsappPhoneNumber::where('phone_number_id', $phoneNumberId)
            ->whereHas('businessAccount', fn ($q) => $q->where('workspace_id', $workspaceId)->where('status', 'active'))
            ->with('businessAccount')
            ->first();

        if (! $phone) {
            Log::warning('CloudApiClient: phone number not linked to workspace', [
                'workspace_id' => $workspaceId,
                'phone_number_id' => $phoneNumberId,
            ]);

            return null;
        }

        $token = $phone->businessAccount->accessToken()
            ?? WhatsappBusinessAccount::resolveAccessTokenForWorkspace($workspaceId);

        if (! $token) {
            Log::warning('CloudApiClient: no access token for phone', [
                'workspace_id' => $workspaceId,
                'phone_number_id' => $phoneNumberId,
            ]);

            return null;
        }

        return new static($phoneNumberId, $token);
    }

    /** Phone number ID used for Graph API sends (matches webhook `metadata.phone_number_id`). */
    public function phoneNumberId(): string
    {
        return $this->phoneNumberId;
    }

    /** Send a text message. */
    public function sendText(string $to, string $body, bool $previewUrl = false): Response
    {
        return $this->post("/{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body, 'preview_url' => $previewUrl],
        ]);
    }

    /** Send a template message. */
    public function sendTemplate(string $to, string $templateName, string $language, array $components = []): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ];

        return $this->post("/{$this->phoneNumberId}/messages", $payload);
    }

    /** Mark a message as read. */
    public function markRead(string $messageId): Response
    {
        return $this->post("/{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    /**
     * List phone numbers attached to a WhatsApp Business Account (no phone_number_id required).
     *
     * @return list<array<string, mixed>>
     */
    public static function fetchWabaPhoneNumbers(string $wabaId, string $accessToken): array
    {
        $resp = Http::withToken($accessToken)
            ->timeout(30)
            ->get(self::BASE."/{$wabaId}/phone_numbers", [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,throughput',
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException(
                'Meta phone_numbers request failed ('.$resp->status().'): '.$resp->body()
            );
        }

        return $resp->json('data', []);
    }

    /**
     * Load a single phone number node by ID (for display name / inbox labels).
     *
     * @return array<string, mixed>|null
     */
    public static function fetchPhoneNumberDetails(string $phoneNumberId, string $accessToken): ?array
    {
        $resp = Http::withToken($accessToken)
            ->timeout(15)
            ->get(self::BASE."/{$phoneNumberId}", [
                'fields' => 'id,display_phone_number,verified_name,quality_rating,throughput,code_verification_status,name_status,requested_verified_name,account_mode',
            ]);

        if (! $resp->successful()) {
            return null;
        }

        $json = $resp->json();

        return is_array($json) ? $json : null;
    }

    /**
     * Register a phone number with the WhatsApp Cloud API.
     *
     * A number added through Embedded Signup is created in a PENDING state and
     * cannot send/receive until it is registered. Registration moves it to
     * CONNECTED (active). The PIN sets/uses the number's two-step verification
     * PIN — for a brand-new number any 6-digit value works; if the number
     * already has two-step verification enabled the matching PIN is required.
     *
     * @return array{success: bool, status: int, response: array<string, mixed>}
     */
    public static function registerPhoneNumber(string $phoneNumberId, string $accessToken, string $pin = '123456'): array
    {
        $resp = Http::withToken($accessToken)
            ->timeout(30)
            ->post(self::BASE."/{$phoneNumberId}/register", [
                'messaging_product' => 'whatsapp',
                'pin' => $pin,
            ]);

        return [
            'success' => $resp->successful(),
            'status' => $resp->status(),
            'response' => $resp->json() ?? [],
        ];
    }

    /** Fetch templates from Meta. */
    public function fetchTemplates(string $wabaId): array
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout(30)
            ->get(self::BASE."/{$wabaId}/message_templates", ['limit' => 200]);

        return $resp->json('data', []);
    }

    /** Check if a phone number has WhatsApp. */
    public function checkContacts(array $phones): array
    {
        $resp = $this->post("/{$this->phoneNumberId}/contacts", [
            'messaging_product' => 'whatsapp',
            'contacts' => $phones,
        ]);

        if (! $resp->successful() || empty($resp->json('contacts'))) {
            Log::warning('WhatsApp checkContacts failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
                'phones' => $phones,
            ]);
        }

        return $resp->json('contacts', []);
    }

    /** Request a display name change for a phone number. */
    public function requestDisplayNameChange(string $phoneNumberId, string $newName): array
    {
        return static::requestDisplayNameChangeDirect($phoneNumberId, $newName, $this->accessToken);
    }

    /** Static variant — uses a bare token directly (bypasses factory). */
    public static function requestDisplayNameChangeDirect(string $phoneNumberId, string $newName, string $token): array
    {
        $resp = Http::withToken($token)
            ->timeout(15)
            ->post(self::BASE."/{$phoneNumberId}", [
                'verified_name' => $newName,
            ]);

        return [
            'success' => $resp->successful(),
            'status' => $resp->status(),
            'response' => $resp->json() ?? [],
        ];
    }

    /** Submit a new template to Meta. */
    public function submitTemplate(string $wabaId, array $template): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(30)
            ->post(self::BASE."/{$wabaId}/message_templates", $template);
    }

    /**
     * Edit an existing template on Meta. Name and language cannot be changed —
     * only category and components are editable. Editing resets the template to PENDING.
     */
    public function editTemplate(string $metaTemplateId, array $template): Response
    {
        $payload = [];
        if (! empty($template['category'])) {
            $payload['category'] = $template['category'];
        }
        if (! empty($template['components'])) {
            $payload['components'] = $template['components'];
        }

        return Http::withToken($this->accessToken)
            ->timeout(30)
            ->post(self::BASE."/{$metaTemplateId}", $payload);
    }

    /** Delete a template from Meta by name (removes every language for that name). */
    public function deleteTemplate(string $wabaId, string $name): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(30)
            ->delete(self::BASE."/{$wabaId}/message_templates", ['name' => $name]);
    }

    /** Upload a media file to the WhatsApp media endpoint. Returns the media ID. */
    public function uploadMedia(string $filePath, string $mimeType): string
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
            ->post(self::BASE."/{$this->phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
            ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('WhatsApp media upload failed: '.$resp->body());
        }

        return $resp->json('id', '');
    }

    /** Resolve a WhatsApp media ID → download URL + mime type. */
    public function getMediaUrl(string $mediaId): array
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout(15)
            ->get(self::BASE."/{$mediaId}", ['phone_number_id' => $this->phoneNumberId]);

        if (! $resp->successful()) {
            throw new \RuntimeException('WhatsApp media lookup failed: '.$resp->body());
        }

        return ['url' => $resp->json('url', ''), 'mime_type' => $resp->json('mime_type', '')];
    }

    /** Download raw bytes from a WhatsApp media URL. */
    public function downloadMedia(string $url): string
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout(60)
            ->get($url);

        if (! $resp->successful()) {
            throw new \RuntimeException('WhatsApp media download failed: '.$resp->status());
        }

        return $resp->body();
    }

    /** Send a media message (image, document, video, audio). */
    public function sendMedia(string $to, string $type, string $mediaId, ?string $caption = null, ?string $filename = null, ?string $link = null): Response
    {
        // Prefer an uploaded media id; fall back to a public URL (image/video/document
        // by link) so callers can share remote media without an upload round-trip.
        $mediaPayload = $mediaId !== '' ? ['id' => $mediaId] : ['link' => (string) $link];
        if ($caption) {
            $mediaPayload['caption'] = $caption;
        }
        if ($filename) {
            $mediaPayload['filename'] = $filename;
        }

        return $this->post("/{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type,
            $type => $mediaPayload,
        ]);
    }

    /** Send a location (map pin) message. */
    public function sendLocation(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null): Response
    {
        $location = ['latitude' => $latitude, 'longitude' => $longitude];
        if ($name) {
            $location['name'] = $name;
        }
        if ($address) {
            $location['address'] = $address;
        }

        return $this->post("/{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'location',
            'location' => $location,
        ]);
    }

    /** Send an interactive (button / list) message. */
    public function sendInteractive(string $to, array $interactive): Response
    {
        return $this->post("/{$this->phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ]);
    }

    /** Verify that the access token and WABA are valid. */
    public function verifyCreds(string $wabaId): bool
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout(10)
            ->get(self::BASE."/{$wabaId}", ['fields' => 'id,name']);

        return $resp->successful() && ! empty($resp->json('id'));
    }

    /**
     * Upload a file via Meta Resumable Upload API and return the header_handle string
     * (e.g. "4::aW1hZ2U...") used as example.header_handle in template submissions.
     */
    public static function resumableUpload(string $appId, string $accessToken, string $filePath, string $mimeType): string
    {
        $fileSize = filesize($filePath);
        if ($fileSize === false) {
            throw new \RuntimeException('Cannot read file: '.$filePath);
        }

        $sessionResp = Http::withToken($accessToken)
            ->timeout(30)
            ->post(self::BASE."/{$appId}/uploads", [
                'file_length' => $fileSize,
                'file_type' => $mimeType,
            ]);

        if (! $sessionResp->successful()) {
            throw new \RuntimeException('Meta upload session failed ('.$sessionResp->status().'): '.$sessionResp->body());
        }

        $sessionId = $sessionResp->json('id', '');
        if (empty($sessionId)) {
            throw new \RuntimeException('Meta upload session returned no id: '.$sessionResp->body());
        }

        $fileContents = file_get_contents($filePath);
        $uploadResp = Http::withHeaders([
            'Authorization' => 'OAuth '.$accessToken,
            'file_offset' => '0',
            'Content-Type' => $mimeType,
        ])
            ->timeout(120)
            ->withBody($fileContents, $mimeType)
            ->post(self::BASE.'/'.$sessionId);

        if (! $uploadResp->successful()) {
            throw new \RuntimeException('Meta resumable upload failed ('.$uploadResp->status().'): '.$uploadResp->body());
        }

        $handle = $uploadResp->json('h', '');
        if (empty($handle)) {
            throw new \RuntimeException('Meta resumable upload returned no handle: '.$uploadResp->body());
        }

        return $handle;
    }

    private function post(string $path, array $data): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(30)
            ->post(self::BASE.$path, $data);
    }
}
