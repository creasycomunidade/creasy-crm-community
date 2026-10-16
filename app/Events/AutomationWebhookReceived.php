<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AutomationWebhookReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $automationId,
        public readonly array $payload,
        public readonly ?int $contactId = null,
    ) {}
}
