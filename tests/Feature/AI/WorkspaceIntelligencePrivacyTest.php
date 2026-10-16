<?php

namespace Tests\Feature\AI;

use App\Modules\AI\Services\WorkspaceIntelligenceContext;
use App\Modules\Shared\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceIntelligencePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_broad_contact_question_returns_counts_without_contact_pii(): void
    {
        $context = $this->createWorkspaceContext();
        Contact::create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Segredo',
            'last_name' => 'LGPD',
            'phone_e164' => '+5547999999999',
            'email' => 'privado@example.com',
        ]);

        $prompt = app(WorkspaceIntelligenceContext::class)
            ->build($context['workspace']->id, 'Quantos contatos e leads temos?');

        $this->assertStringContainsString('1 contato(s)', $prompt);
        $this->assertStringNotContainsString('Segredo', $prompt);
        $this->assertStringNotContainsString('+5547999999999', $prompt);
        $this->assertStringNotContainsString('privado@example.com', $prompt);
    }

    public function test_named_contact_question_loads_only_the_matching_contact(): void
    {
        $context = $this->createWorkspaceContext();
        Contact::create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Marina',
            'last_name' => 'Alves',
            'phone_e164' => '+5547999991111',
        ]);
        Contact::create([
            'workspace_id' => $context['workspace']->id,
            'first_name' => 'Pessoa',
            'last_name' => 'Não Relacionada',
            'phone_e164' => '+5547999992222',
        ]);

        $prompt = app(WorkspaceIntelligenceContext::class)
            ->build($context['workspace']->id, 'Qual o telefone da cliente Marina?');

        $this->assertStringContainsString('Marina Alves', $prompt);
        $this->assertStringContainsString('+5547999991111', $prompt);
        $this->assertStringNotContainsString('Pessoa Não Relacionada', $prompt);
        $this->assertStringNotContainsString('+5547999992222', $prompt);
    }
}
