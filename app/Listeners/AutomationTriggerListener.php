<?php

namespace App\Listeners;

use App\Events\AutomationWebhookReceived;
use App\Events\CampaignCompleted;
use App\Events\CommerceEventReceived;
use App\Events\ContactCreated;
use App\Events\MessageReceived;
use App\Modules\Automation\Jobs\ExecuteAutomationRunJob;
use App\Modules\Automation\Models\Automation;
use App\Modules\Automation\Models\AutomationRun;
use App\Modules\Automation\Services\AutomationEngine;

class AutomationTriggerListener
{
    public function __construct(private readonly AutomationEngine $engine) {}

    public function handleMessageReceived(MessageReceived $event): void
    {
        $contactId = $event->message->conversation?->contact_id;
        $workspaceId = $event->message->conversation?->workspace_id;
        if (! $contactId || ! $workspaceId) {
            return;
        }

        $messageBody = $event->message->body ?? '';

        // Resume any runs parked on an "Ask question" node awaiting this contact's reply.
        $this->engine->resumeAwaitingReplies($workspaceId, $contactId, $messageBody);

        $this->fireWithConfig('message.received', $workspaceId, $contactId, [
            'message_id' => $event->message->id,
            'message_channel' => $event->message->channel,
            'message_body' => $messageBody,
        ], $messageBody);
    }

    public function handleContactCreated(ContactCreated $event): void
    {
        $this->fire('contact.created', $event->contact->workspace_id, $event->contact->id);
    }

    public function handleCampaignCompleted(CampaignCompleted $event): void
    {
        // No per-contact trigger for campaign completion; skip.
    }

    public function handleCommerceEvent(CommerceEventReceived $event): void
    {
        // eventType is one of order.placed / order.fulfilled / order.cancelled /
        // cart.abandoned / customer.created — matched directly against trigger_type.
        $this->fire($event->eventType, $event->workspaceId, $event->contactId, $event->context);
    }

    public function handleAutomationWebhookReceived(AutomationWebhookReceived $event): void
    {
        $automation = Automation::where('id', $event->automationId)
            ->where('status', 'active')
            ->whereIn('trigger_type', ['webhook', 'webhook.received'])
            ->first();

        if (! $automation) {
            return;
        }

        $context = ['payload' => $event->payload];

        if ($event->contactId) {
            $this->engine->triggerForContact($automation, $event->contactId, $context);
        } else {
            // Contactless: trigger a run without a contact (contact_id = null)
            $this->triggerWithoutContact($automation, $context);
        }
    }

    private function triggerWithoutContact(Automation $automation, array $context = []): void
    {
        $run = AutomationRun::create([
            'automation_id' => $automation->id,
            'contact_id' => null,
            'status' => 'pending',
            'context' => $context,
            'started_at' => now(),
        ]);

        dispatch(new ExecuteAutomationRunJob($run->id))->onQueue('automation');
    }

    private function fire(string $triggerType, int $workspaceId, int $contactId, array $context = []): void
    {
        Automation::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->where('trigger_type', $triggerType)
            ->each(fn ($automation) => $this->engine->triggerForContact($automation, $contactId, $context));
    }

    /**
     * Like fire(), but respects trigger_config.keywords for message.received automations.
     * If keywords are set, the message body must contain at least one keyword (case-insensitive).
     */
    private function fireWithConfig(string $triggerType, int $workspaceId, int $contactId, array $context, string $messageBody = ''): void
    {
        $automations = Automation::where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->where('trigger_type', $triggerType)
            ->get();

        $bodyLower = mb_strtolower($messageBody);

        foreach ($automations as $automation) {
            $keywords = $automation->trigger_config['keywords'] ?? [];

            if (! empty($keywords)) {
                $matches = false;
                foreach ($keywords as $kw) {
                    if (str_contains($bodyLower, mb_strtolower((string) $kw))) {
                        $matches = true;
                        break;
                    }
                }
                if (! $matches) {
                    continue;
                }
            }

            $this->engine->triggerForContact($automation, $contactId, $context);
        }
    }
}
