<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a normalized commerce event (order placed/fulfilled/cancelled,
 * cart abandoned, customer created) is ready to drive automations.
 *
 * The eventType maps directly to an Automation trigger_type, and $context is a
 * flat key/value map consumed by {{context.x}} message tokens.
 */
class CommerceEventReceived
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, string>  $context
     */
    public function __construct(
        public readonly int $workspaceId,
        public readonly int $contactId,
        public readonly string $eventType,
        public readonly array $context = [],
    ) {}
}
