<?php

namespace App\Support;

final class PlanModuleCatalog
{
    public static function all(): array
    {
        return [
            'inbox' => ['label' => 'Caixa de entrada', 'description' => 'Conversas omnichannel, notas, pedidos e respostas.'],
            'message_scheduler' => ['label' => 'Agendador de mensagens', 'description' => 'Mensagens futuras, recorrentes, pausa e edição.'],
            'channel_management' => ['label' => 'Configuração de canais', 'description' => 'WhatsApp, Instagram, Messenger, Telegram e demais canais.'],
            'whatsapp_tools' => ['label' => 'Ferramentas do WhatsApp', 'description' => 'Modelos, respostas automáticas e widget.'],
            'contacts' => ['label' => 'Contatos e segmentos', 'description' => 'Cadastro, importação, etiquetas e públicos segmentados.'],
            'crm' => ['label' => 'CRM e tarefas', 'description' => 'Funil de vendas, negócios, quadros e tarefas.'],
            'broadcasting' => ['label' => 'Campanhas e transmissão', 'description' => 'Campanhas por WhatsApp, Instagram, Messenger, Telegram, SMS e e-mail.'],
            'appointments' => ['label' => 'Agenda e agendamentos', 'description' => 'Calendário, serviços, profissionais e páginas públicas.'],
            'automations' => ['label' => 'Automações', 'description' => 'Fluxos, gatilhos, execuções e automações com IA.'],
            'ecommerce' => ['label' => 'E-commerce', 'description' => 'Lojas, produtos, pedidos e carrinhos abandonados.'],
            'ai' => ['label' => 'Inteligência artificial', 'description' => 'Agentes, chatbots, conhecimento, arquivos e rotinas.'],
            'ai_extensions' => ['label' => 'Skills e MCP para IA', 'description' => 'Competências personalizadas e ferramentas externas para os agentes.'],
            'second_brain' => ['label' => 'Segundo Cérebro', 'description' => 'Memória relacional, correlações e recuperação de contexto para as IAs.'],
            'mind_maps' => ['label' => 'Mapas mentais', 'description' => 'Mapas visuais, brainstorming, tarefas e conexão com o Segundo Cérebro.'],
            'social' => ['label' => 'Redes sociais', 'description' => 'Contas, criação, calendário e publicação social.'],
            'leads' => ['label' => 'Prospecção de leads', 'description' => 'Extração, edição e envio de leads para contatos.'],
            'ads' => ['label' => 'Anúncios e atribuição', 'description' => 'Meta Ads, Google Ads, rastreamento e conversões.'],
            'reports' => ['label' => 'Relatórios', 'description' => 'Relatórios de atendimento, campanhas, IA e redes sociais.'],
            'integrations' => ['label' => 'Integrações', 'description' => 'Conexões externas, webhooks e integrações do espaço.'],
            'unified_connections' => ['label' => 'Canais unificados', 'description' => 'Conexões isoladas por empresa para canais, publicações e anúncios.'],
            'webhooks' => ['label' => 'Webhooks', 'description' => 'Eventos de saída e integrações por webhook.'],
            'developer_api' => ['label' => 'API e webhooks', 'description' => 'Tokens de API, documentação e webhooks de saída.'],
            'media_library' => ['label' => 'Biblioteca de mídia', 'description' => 'Arquivos e mídias reutilizáveis.'],
            'fazos' => ['label' => 'Integração FazOS', 'description' => 'Ordens de serviço e vínculos com conversas.'],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function enabledDefaults(): array
    {
        return CommunityEdition::modules();
    }
}
