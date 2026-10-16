<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\WorkspaceExportReadyNotification;
use App\Services\WorkspaceExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateWorkspaceExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(private int $userId) {}

    public function handle(WorkspaceExportService $exportService): void
    {
        $user = User::findOrFail($this->userId);

        $storagePath = $exportService->generate($user);

        // Create a 72-hour signed URL so only the requester can download it
        $signedUrl = Storage::temporaryUrl($storagePath, now()->addHours(72));

        $user->notify(new WorkspaceExportReadyNotification($signedUrl));
    }
}
