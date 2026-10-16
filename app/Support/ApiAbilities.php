<?php

namespace App\Support;

/**
 * Canonical list of Sanctum token ability scopes for the /api/v1 surface.
 * Used in token creation UI (Tokens.jsx), CheckApiAbility middleware,
 * route declarations, and OpenAPI documentation.
 */
final class ApiAbilities
{
    // Contacts & Segments
    public const CONTACTS_READ = 'contacts:read';

    public const CONTACTS_WRITE = 'contacts:write';

    // Campaigns
    public const CAMPAIGNS_READ = 'campaigns:read';

    public const CAMPAIGNS_WRITE = 'campaigns:write';

    // Outbound messaging
    public const MESSAGES_WRITE = 'messages:write';

    // Inbox (read-only)
    public const CONVERSATIONS_READ = 'conversations:read';

    // Webhooks
    public const WEBHOOKS_WRITE = 'webhooks:write';

    // AI / Knowledge Bases
    public const AI_READ = 'ai:read';

    public const AI_WRITE = 'ai:write';

    // Automations
    public const AUTOMATIONS_WRITE = 'automations:write';

    // Social Media
    public const SOCIAL_WRITE = 'social:write';

    // Analytics (read-only)
    public const ANALYTICS_READ = 'analytics:read';

    /** All scopes as a flat array for UI dropdowns and validation. */
    public static function all(): array
    {
        return [
            self::CONTACTS_READ,
            self::CONTACTS_WRITE,
            self::CAMPAIGNS_READ,
            self::CAMPAIGNS_WRITE,
            self::MESSAGES_WRITE,
            self::CONVERSATIONS_READ,
            self::WEBHOOKS_WRITE,
            self::AI_READ,
            self::AI_WRITE,
            'mcp:n8n:read',
            'mcp:n8n:write',
            self::AUTOMATIONS_WRITE,
            self::SOCIAL_WRITE,
            self::ANALYTICS_READ,
        ];
    }

    /** Human-readable labels for the token creation UI. */
    public static function labels(): array
    {
        return [
            'mcp:n8n:read' => 'Consultar workflows e execuções pelo MCP n8n',
            'mcp:n8n:write' => 'Criar, editar e acionar automações pelo MCP n8n',
            self::CONTACTS_READ => 'Read contacts & segments',
            self::CONTACTS_WRITE => 'Create & update contacts and segments',
            self::CAMPAIGNS_READ => 'Read campaigns and recipient stats',
            self::CAMPAIGNS_WRITE => 'Create, launch, and pause campaigns',
            self::MESSAGES_WRITE => 'Send outbound messages (WhatsApp / SMS / Email)',
            self::CONVERSATIONS_READ => 'Read inbox conversations and message threads',
            self::WEBHOOKS_WRITE => 'Register and remove outbound webhook endpoints',
            self::AI_READ => 'Read AI chatbots and knowledge bases',
            self::AI_WRITE => 'Invoke chatbots and manage knowledge base documents',
            self::AUTOMATIONS_WRITE => 'Trigger automations for a contact',
            self::SOCIAL_WRITE => 'Schedule social media posts',
            self::ANALYTICS_READ => 'Read analytics and reporting metrics',
        ];
    }
}
