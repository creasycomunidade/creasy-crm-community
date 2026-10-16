<?php

namespace App\Http\Controllers\Client\Reports;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Modules\AI\Models\AiRun;
use App\Modules\Broadcasting\Models\Campaign;
use App\Modules\Broadcasting\Models\CampaignRecipient;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Conversation;
use Illuminate\Http\Request;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function contacts(Request $request): StreamedResponse
    {
        $wsId = $request->user()->workspace_id;
        abort_if(! $wsId, 403);

        return $this->streamCsv('contacts.csv', function (Writer $csv) use ($wsId) {
            $csv->insertOne(['ID', 'First Name', 'Last Name', 'Phone', 'Email', 'Tags', 'Created At']);

            Contact::where('workspace_id', $wsId)
                ->orderBy('id')
                ->chunk(500, function ($contacts) use ($csv) {
                    foreach ($contacts as $c) {
                        $csv->insertOne([
                            $c->id,
                            $c->first_name,
                            $c->last_name,
                            $c->phone_e164,
                            $c->email,
                            is_array($c->tags) ? implode(', ', $c->tags) : ($c->tags ?? ''),
                            $c->created_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }

    public function campaignRecipients(Request $request, Campaign $campaign): StreamedResponse
    {
        abort_if($campaign->workspace_id !== $request->user()->workspace_id, 403);

        return $this->streamCsv("campaign-{$campaign->id}-recipients.csv", function (Writer $csv) use ($campaign) {
            $csv->insertOne(['Recipient ID', 'Contact ID', 'Status', 'Sent At', 'Delivered At', 'Read At', 'Failed Reason']);

            CampaignRecipient::where('campaign_id', $campaign->id)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($csv) {
                    foreach ($rows as $r) {
                        $csv->insertOne([
                            $r->id,
                            $r->contact_id,
                            $r->status,
                            $r->sent_at?->toIso8601String(),
                            $r->delivered_at?->toIso8601String(),
                            $r->read_at?->toIso8601String(),
                            $r->failed_reason,
                        ]);
                    }
                });
        });
    }

    public function conversations(Request $request): StreamedResponse
    {
        $wsId = $request->user()->workspace_id;
        abort_if(! $wsId, 403);

        return $this->streamCsv('conversations.csv', function (Writer $csv) use ($wsId) {
            $csv->insertOne(['ID', 'Channel', 'Contact ID', 'Status', 'Assigned User ID', 'Last Message At', 'Created At']);

            Conversation::where('workspace_id', $wsId)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($csv) {
                    foreach ($rows as $c) {
                        $csv->insertOne([
                            $c->id,
                            $c->channelAccount?->channel ?? '',
                            $c->contact_id,
                            $c->status,
                            $c->assigned_user_id,
                            $c->last_message_at?->toIso8601String(),
                            $c->created_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }

    public function aiRuns(Request $request): StreamedResponse
    {
        $wsId = $request->user()->workspace_id;
        abort_if(! $wsId, 403);

        return $this->streamCsv('ai-runs.csv', function (Writer $csv) use ($wsId) {
            $csv->insertOne(['ID', 'Chatbot ID', 'Model', 'Prompt Tokens', 'Completion Tokens', 'Cost Cents', 'Latency ms', 'Status', 'Created At']);

            AiRun::query()
                ->join('ai_chatbots', 'ai_chatbots.id', '=', 'ai_runs.chatbot_id')
                ->where('ai_chatbots.workspace_id', $wsId)
                ->select('ai_runs.*')
                ->orderBy('ai_runs.id')
                ->chunk(500, function ($rows) use ($csv) {
                    foreach ($rows as $r) {
                        $csv->insertOne([
                            $r->id, $r->chatbot_id, $r->model,
                            $r->prompt_tokens, $r->completion_tokens,
                            $r->cost_cents, $r->latency_ms, $r->status,
                            $r->created_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }

    public function auditLog(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_if(! $user->isClientAdministrator(), 403);

        $clientId = $user->client_id;

        return $this->streamCsv('audit-log.csv', function (Writer $csv) use ($clientId) {
            $csv->insertOne(['ID', 'Actor User ID', 'Client ID', 'Action', 'Auditable Type', 'Auditable ID', 'IP', 'Created At']);

            AuditLog::where('client_id', $clientId)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($csv) {
                    foreach ($rows as $r) {
                        $csv->insertOne([
                            $r->id, $r->user_id, $r->client_id,
                            $r->action, $r->auditable_type, $r->auditable_id,
                            $r->ip, $r->created_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }

    // ──────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────────

    private function streamCsv(string $filename, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($writer) {
            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            $writer($csv);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
