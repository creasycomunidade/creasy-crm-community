<?php

namespace App\Modules\Whatsapp\Jobs;

use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use App\Modules\Whatsapp\Models\WhatsappTemplate;
use App\Modules\Whatsapp\Services\CloudApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TemplateSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $wabaDbId) {}

    public function handle(): void
    {
        $waba = WhatsappBusinessAccount::find($this->wabaDbId);
        if (! $waba) {
            return;
        }

        $client = CloudApiClient::forWorkspace($waba->workspace_id);
        if (! $client) {
            Log::warning('TemplateSyncJob: no CloudApiClient for workspace '.$waba->workspace_id);

            return;
        }

        $templates = $client->fetchTemplates($waba->waba_id);

        foreach ($templates as $tpl) {
            WhatsappTemplate::updateOrCreate(
                ['workspace_id' => $waba->workspace_id, 'waba_id' => $waba->waba_id, 'name' => $tpl['name'], 'language' => $tpl['language']],
                [
                    'category' => $tpl['category'] ?? 'MARKETING',
                    'status' => $tpl['status'] ?? 'PENDING',
                    'components' => $tpl['components'] ?? [],
                    'rejection_reason' => $tpl['rejection_reason'] ?? null,
                    'meta_template_id' => $tpl['id'] ?? null,
                ]
            );
        }
    }
}
