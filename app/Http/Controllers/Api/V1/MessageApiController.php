<?php

namespace App\Http\Controllers\Api\V1;

use App\Modules\Broadcasting\Services\Sms\SmsDriverManager;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Message;
use App\Modules\Whatsapp\Services\CloudApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageApiController extends WorkspaceScopedController
{
    /**
     * POST /api/v1/messages/send
     * Outbound transactional message to a known contact.
     */
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer'],
            'channel' => ['required', 'string', 'in:whatsapp,sms,email'],
            'type' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'template_name' => ['nullable', 'string'],
            'template_vars' => ['nullable', 'array'],
        ]);

        $wsId = $this->workspaceId($request);

        $contact = Contact::where('workspace_id', $wsId)->find($validated['contact_id']);
        if (! $contact) {
            return response()->json(['error' => 'Contact not found.'], 404);
        }

        $channel = $validated['channel'];

        try {
            $providerMessageId = match ($channel) {
                'whatsapp' => $this->sendWhatsapp($wsId, $contact, $validated),
                'sms' => $this->sendSms($wsId, $contact, $validated),
                default => throw new \InvalidArgumentException("Channel '{$channel}' send not yet implemented."),
            };
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'provider_message_id' => $providerMessageId,
            'status' => 'sent',
        ]);
    }

    private function sendWhatsapp(int $wsId, Contact $contact, array $payload): string
    {
        $client = CloudApiClient::forWorkspace($wsId);
        if (! $client) {
            throw new \RuntimeException('No active WhatsApp channel account for this workspace.');
        }

        $phone = $contact->phone_e164;
        if (! $phone) {
            throw new \RuntimeException('Contact has no E.164 phone number.');
        }

        if (! empty($payload['template_name'])) {
            $vars = $payload['template_vars'] ?? [];
            $components = [];
            if (! empty($vars)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($vars)),
                ];
            }
            $resp = $client->sendTemplate($phone, $payload['template_name'], 'en', $components);
        } else {
            $resp = $client->sendText($phone, $payload['body'] ?? '');
        }

        if (! $resp->successful()) {
            throw new \RuntimeException('WhatsApp send failed: '.$resp->body());
        }

        return $resp->json('messages.0.id', '');
    }

    private function sendSms(int $wsId, Contact $contact, array $payload): string
    {
        $phone = $contact->phone_e164;
        if (! $phone) {
            throw new \RuntimeException('Contact has no E.164 phone number.');
        }

        $driver = SmsDriverManager::forWorkspace($wsId);
        $result = $driver->send($phone, $payload['body'] ?? '');

        if (! $result->success) {
            throw new \RuntimeException('SMS send failed: '.$result->error);
        }

        return $result->messageId;
    }
}
