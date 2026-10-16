<?php

namespace Tests\Feature\Community;

use App\Support\CommunityEdition;
use App\Support\PlanModuleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityEditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_capabilities_do_not_depend_on_a_paid_plan(): void
    {
        $this->assertTrue(CommunityEdition::allows('inbox'));
        $this->assertTrue(CommunityEdition::allows('ai'));
        $this->assertFalse(PlanModuleCatalog::enabledDefaults()['broadcasting']);
    }

    public function test_premium_web_and_api_requests_are_unavailable(): void
    {
        foreach (['/painel/ai/agents', '/painel/ads', '/painel/integracoes', '/admin/plans', '/api/v1/ai/chatbots', '/api/v1/campaigns', '/painel/reports/ai'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        $this->postJson('/api/v1/automations/1/trigger', ['contact_id' => 1])->assertNotFound();
        $this->postJson('/webhooks/stripe')->assertNotFound();
    }

    public function test_free_inbox_contacts_and_crm_remain_accessible_without_subscription(): void
    {
        ['user' => $user] = $this->createWorkspaceContext();
        foreach (['/painel/inbox', '/painel/contacts', '/painel/crm/pipelines', '/painel/inbox/setup', '/painel/reports/inbox', '/painel/onboarding', '/painel/crm/funil', '/painel/inbox/setup/webchat'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        $this->actingAs($user)->get('/painel/inbox')->assertInertia(fn (Assert $page) => $page
            ->where('planModules.ai', true)
            ->where('planModules.crm', true)
            ->where('planLimits', []));
        $this->assertNull($user->effectiveSubscription());
    }

    public function test_administrator_can_configure_the_installation(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin, 'admin')->get('/admin/dashboard')->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin/clients')->assertOk();
        $this->actingAs($admin, 'admin')->get('/admin/settings')->assertOk();
    }

    public function test_prefix_gate_does_not_disable_channel_webhooks_or_manual_crm(): void
    {
        foreach (['webhooks/whatsapp/global', 'webhooks/evolution-go/token', 'webhooks/whatsapp-gateway/token', 'widgets/chat/key/messages', 'painel/crm/pipelines', 'painel/inbox/conversations/id/reply'] as $path) {
            $this->assertFalse(CommunityEdition::blocksPath($path), $path);
        }
    }
}
