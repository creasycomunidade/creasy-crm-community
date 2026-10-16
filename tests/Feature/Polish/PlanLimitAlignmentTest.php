<?php

namespace Tests\Feature\Polish;

use App\Modules\Broadcasting\Models\UsageMeter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlanLimitAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_meter_tracks_whatsapp_messages(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $workspace = $ctx['workspace'];

        UsageMeter::track($workspace->id, 'whatsapp_messages', 3);

        $total = DB::table('usage_meters')
            ->where('workspace_id', $workspace->id)
            ->where('metric', 'whatsapp_messages')
            ->sum('value');

        $this->assertEquals(3, $total);
    }

    public function test_usage_meter_tracks_social_posts(): void
    {
        $ctx = $this->createWorkspaceContext([], ['email_verified_at' => now()]);
        $workspace = $ctx['workspace'];

        UsageMeter::track($workspace->id, 'social_posts', 1);

        $total = DB::table('usage_meters')
            ->where('workspace_id', $workspace->id)
            ->where('metric', 'social_posts')
            ->sum('value');

        $this->assertEquals(1, $total);
    }
}
