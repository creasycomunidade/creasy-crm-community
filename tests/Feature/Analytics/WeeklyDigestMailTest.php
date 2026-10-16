<?php

namespace Tests\Feature\Analytics;

use App\Mail\WeeklyDigestMail;
use App\Models\ClientSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WeeklyDigestMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_digest_mail_renders_without_error(): void
    {
        $ctx = $this->createWorkspaceContext();
        $workspace = $ctx['workspace'];

        $stats = [
            'messages_sent' => 120,
            'delivery_rate' => 94.5,
            'conversations_opened' => 30,
            'conversations_resolved' => 25,
            'ai_runs' => 45,
            'ai_tokens' => 8000,
            'ai_cost_usd' => 0.24,
            'top_campaign' => ['name' => 'Summer Sale', 'delivered_pct' => 91.0],
            'top_chatbot' => ['name' => 'Support Bot', 'runs' => 40],
        ];

        $mailable = new WeeklyDigestMail(
            workspace: $workspace,
            stats: $stats,
            period: 'Apr 21–27, 2026',
            dashboardUrl: 'http://test/app/dashboard',
            settingsUrl: 'http://test/app/settings',
        );

        // Check envelope
        $this->assertStringContainsString($workspace->name, $mailable->envelope()->subject);

        // Render without throwing
        $rendered = $mailable->render();
        $this->assertStringContainsString('Relatório semanal', $rendered);
        $this->assertStringContainsString('120', $rendered);
    }

    public function test_weekly_digest_command_respects_opt_out(): void
    {
        Mail::fake();

        $ctx = $this->createWorkspaceContext();
        $client = $ctx['client'];

        // Disable digest for this client
        ClientSetting::set($client->id, 'weekly_digest_enabled', '0');

        $this->artisan('reports:weekly-digest')->assertExitCode(0);

        Mail::assertNothingQueued();
    }
}
