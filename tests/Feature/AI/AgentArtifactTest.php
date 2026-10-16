<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Models\AiAgentArtifact;
use App\Modules\AI\Services\AgentArtifactPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentArtifactTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_is_sanitized_and_can_be_shared_and_downloaded(): void
    {
        $context = $this->createWorkspaceContext();
        $publisher = app(AgentArtifactPublisher::class);
        $artifact = $publisher->create(
            $context['workspace']->id,
            'Relatório comercial',
            '<main onclick="alert(1)"><h1>Resultado</h1><script>alert(1)</script><a href="javascript:alert(2)">Abrir</a><form><input></form></main>',
            now()->addDays(7),
            user: $context['user'],
        );

        $html = $artifact->html_content;
        $this->assertStringContainsString('Resultado', $html);
        $this->assertStringNotContainsStringIgnoringCase('<script', $html);
        $this->assertStringNotContainsStringIgnoringCase('onclick=', $html);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $html);
        $this->assertStringNotContainsStringIgnoringCase('<form', $html);

        $this->get(route('public.agent-artifacts.show', $artifact->token))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertSee('Resultado');

        $this->assertSame(1, $artifact->fresh()->views);

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->get(route('client.ai.agent-links.download', $artifact))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="relatorio-comercial.html"');
    }

    public function test_expired_or_removed_links_are_immediately_unavailable(): void
    {
        $context = $this->createWorkspaceContext();
        $publisher = app(AgentArtifactPublisher::class);
        $expired = $publisher->create(
            $context['workspace']->id,
            'Expirado',
            '<h1>Expirado</h1>',
            now()->subMinute(),
            user: $context['user'],
        );

        $this->get(route('public.agent-artifacts.show', $expired->token))->assertNotFound();

        $active = $publisher->create(
            $context['workspace']->id,
            'Ativo',
            '<h1>Ativo</h1>',
            null,
            user: $context['user'],
        );

        $this->actingAs($context['user'])
            ->withSession(['current_workspace_id' => $context['workspace']->id])
            ->delete(route('client.ai.agent-links.destroy', $active))
            ->assertRedirect();

        $this->get(route('public.agent-artifacts.show', $active->token))->assertNotFound();
    }

    public function test_another_workspace_cannot_download_or_remove_an_artifact(): void
    {
        $owner = $this->createWorkspaceContext();
        $other = $this->createWorkspaceContext();
        $artifact = app(AgentArtifactPublisher::class)->create(
            $owner['workspace']->id,
            'Documento privado',
            '<h1>Somente o proprietário</h1>',
            null,
            user: $owner['user'],
        );

        $this->actingAs($other['user'])
            ->withSession(['current_workspace_id' => $other['workspace']->id])
            ->get(route('client.ai.agent-links.download', $artifact))
            ->assertForbidden();

        $this->actingAs($other['user'])
            ->withSession(['current_workspace_id' => $other['workspace']->id])
            ->delete(route('client.ai.agent-links.destroy', $artifact))
            ->assertForbidden();

        $this->assertNull($artifact->fresh()->revoked_at);
    }

    public function test_agent_html_block_becomes_a_temporary_or_permanent_link(): void
    {
        $context = $this->createWorkspaceContext();
        $agent = \App\Modules\AI\Models\AiAgent::create([
            'workspace_id' => $context['workspace']->id,
            'name' => 'Maestro',
            'slug' => 'maestro-'.uniqid(),
            'layer' => 'business',
            'description' => 'Coordena tarefas.',
            'persona' => 'Coordenador principal.',
            'system_prompt' => 'Ajude o usuário.',
            'enabled' => true,
        ]);

        $result = app(AgentArtifactPublisher::class)->processAgentReply(
            $agent,
            $context['user'],
            'Crie um link fixo para este relatório',
            "Relatório pronto.\n```respondesk-html\n<h1>Fechamento de julho</h1>\n```",
        );

        $this->assertStringContainsString('Página publicada:', $result['reply']);
        $this->assertStringContainsString('Link permanente.', $result['reply']);
        $this->assertStringNotContainsString('```respondesk-html', $result['reply']);
        $this->assertNull(AiAgentArtifact::where('uuid', $result['artifact']['uuid'])->firstOrFail()->expires_at);
    }
}
