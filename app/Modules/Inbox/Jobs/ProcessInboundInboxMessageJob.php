<?php

namespace App\Modules\Inbox\Jobs;

use App\Modules\Inbox\Services\InstagramDriver;
use App\Modules\Inbox\Services\MessengerDriver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundInboxMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Retry up to 5 times on transient failures. */
    public int $tries = 5;

    /** Hard timeout per attempt. */
    public int $timeout = 120;

    /** Max unhandled exceptions before marking failed without retrying. */
    public int $maxExceptions = 3;

    /** Exponential back-off in seconds. */
    public function backoff(): array
    {
        return [30, 60, 120, 240, 300];
    }

    public function __construct(
        private readonly array $payload,
        private readonly string $object,
    ) {}

    public function handle(InstagramDriver $instagram, MessengerDriver $messenger): void
    {
        match ($this->object) {
            'instagram' => $instagram->processWebhookPayload($this->payload),
            'page'      => $messenger->processWebhookPayload($this->payload),
            default     => null,
        };
    }
}
