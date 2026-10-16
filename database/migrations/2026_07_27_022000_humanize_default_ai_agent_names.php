<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $names = [
            'clawdia-assistant' => 'Assistente Executivo',
            'flux-finance' => 'Especialista Financeiro',
            'atlas-project' => 'Gestor de Projetos',
            'pulse-community' => 'Gestor de Comunidade',
            'pixel-social-media' => 'Especialista em Redes Sociais',
            'sage-strategy' => 'Consultor Estratégico',
            'nex-sales' => 'Consultor de Vendas',
            'mentor-courses' => 'Tutor de Cursos',
            'lumen-learning' => 'Tutor de Aprendizagem',
            'kai-personal-assistant' => 'Assistente Pessoal',
            'oracle' => 'Especialista em Conhecimento',
            'mako-marketing' => 'Especialista em Marketing',
            'aria-hr' => 'Especialista de RH',
            'zara-cs' => 'Especialista em Sucesso do Cliente',
            'lex-legal' => 'Assistente Jurídico',
            'nova-product' => 'Gerente de Produto',
            'dex-data' => 'Analista de Dados',
            'apex-architect' => 'Arquiteto de Software',
            'echo-analyst' => 'Analista de Sistemas',
            'compass-planner' => 'Planejador Técnico',
            'raven-critic' => 'Revisor Crítico',
            'bolt-executor' => 'Desenvolvedor',
            'hawk-debugger' => 'Especialista em Diagnóstico',
            'lens-reviewer' => 'Revisor de Código',
            'zen-simplifier' => 'Especialista em Refatoração',
            'vault-security' => 'Analista de Segurança',
            'grid-tester' => 'Engenheiro de Testes',
            'probe-qa' => 'Analista de Qualidade',
            'oath-verifier' => 'Verificador de Entregas',
            'trail-tracer' => 'Investigador Técnico',
            'scout-explorer' => 'Explorador de Código',
            'flow-git' => 'Especialista em Git',
            'scroll-docs' => 'Especialista em Documentação',
            'quill-writer' => 'Redator Técnico',
            'canvas-designer' => 'Designer de Produto',
            'prism-scientist' => 'Cientista de Dados',
        ];

        foreach ($names as $slug => $name) {
            DB::table('ai_agents')->where('slug', $slug)->update(['name' => $name]);
        }
    }

    public function down(): void
    {
        // Os nomes podem ter sido personalizados pelo usuário; não os revertemos.
    }
};
