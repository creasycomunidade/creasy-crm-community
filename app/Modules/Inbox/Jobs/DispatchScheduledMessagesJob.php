<?php

namespace App\Modules\Inbox\Jobs;

use App\Modules\Inbox\Models\ScheduledMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchScheduledMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $this->dueIds()
            ->each(fn (int $id) => SendScheduledMessageJob::dispatch($id)->onQueue('whatsapp'));
    }

    public function handleSynchronously(): void
    {
        $this->dueIds()
            ->each(fn (int $id) => app()->call([new SendScheduledMessageJob($id), 'handle']));
    }

    private function dueIds()
    {
        return ScheduledMessage::query()
            ->where(function ($query) {
                $query->where('status', 'scheduled')
                    ->where('next_send_at', '<=', now());
            })
            ->orWhere(function ($query) {
                $query->where('status', 'processing')
                    ->where('processing_at', '<=', now()->subMinutes(10));
            })
            ->orderBy('next_send_at')
            ->limit(200)
            ->pluck('id');
    }
}
