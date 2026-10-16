<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\AutomationWebhookReceived;
use App\Modules\Automation\Models\Automation;
use App\Modules\Shared\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationWebhookController
{
    public function receive(Request $request, string $triggerToken): JsonResponse
    {
        $automation = Automation::where('trigger_token', $triggerToken)
            ->where('status', 'active')
            ->first();

        if (! $automation) {
            return response()->json(['error' => 'Automation not found or inactive.'], 404);
        }

        $payload = $request->all();

        // Optionally resolve contact from payload.email or payload.phone
        $contactId = null;
        if (isset($payload['email'])) {
            $contact = Contact::where('workspace_id', $automation->workspace_id)
                ->where('email', $payload['email'])
                ->first();
            $contactId = $contact?->id;
        } elseif (isset($payload['phone'])) {
            $contact = Contact::where('workspace_id', $automation->workspace_id)
                ->where('phone_e164', $payload['phone'])
                ->first();
            $contactId = $contact?->id;
        }

        AutomationWebhookReceived::dispatch($automation->id, $payload, $contactId);

        return response()->json(['status' => 'accepted'], 202);
    }
}
