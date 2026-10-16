<?php

namespace App\Support;

final class CommunityEdition
{
    public const MODULES = ['inbox', 'channel_management', 'whatsapp_tools', 'contacts', 'crm', 'reports', 'media_library', 'ai', 'automations', 'appointments', 'message_scheduler', 'webhooks'];

    public static function allows(string $module): bool
    {
        return in_array($module, self::MODULES, true);
    }

    public static function modules(): array
    {
        return array_combine(PlanModuleCatalog::keys(), array_map(self::allows(...), PlanModuleCatalog::keys()));
    }

    /** Routes kept during extraction must never expose commercial actions. */
    public static function blocksPath(string $path): bool
    {
        $path = trim($path, '/');
        $prefixes = [
             'painel/ai/agents', 'painel/ai/agent-tasks', 'painel/ai/agent-links', 'painel/ai/library', 'painel/ai/operations', 'painel/ads', 'painel/social', 'painel/leads',
             'painel/broadcasts', 'painel/ecommerce',
             'painel/integracoes', 'painel/fazos',
            'painel/subscription', 'painel/billing', 'painel/pricing', 'painel/checkout', 'painel/coupon',
            'painel/workspace-files', 'painel/workspace-folders', 'painel/workspace-file-shares',
            'painel/api-tokens', 'painel/api-docs', 
            
            'admin/ai', 'admin/plans', 'admin/subscriptions', 'admin/payments', 'admin/payment-gateways',
            'admin/coupons', 'admin/tax-rates', 'admin/creasy-license',
            'api/v1/ai', 'api/v1/automations', 'api/v1/campaigns', 'api/v1/social',
            'api/v1/tokens', 'api/v1/token-scopes', 'api/v1/webhooks', 'api/v1/subscription', 'api/v1/usage',
            'api/v1/analytics/ai-usage', 'api/v1/analytics/campaign',
             'webhooks/ecommerce', 'webhooks/sms', 'webhooks/zernio',
            'webhooks/stripe', 'webhooks/paypal', 'webhooks/paddle', 'webhooks/razorpay',
            'webhooks/cashfree', 'webhooks/tap', 'webhooks/paystack', 'webhooks/xendit',
            'webhooks/paymob', 'webhooks/myfatoorah', 'webhooks/mollie', 'webhooks/square', 'webhooks/mercadopago',
             'rastreio', 'track/email', 'f', 'pricing',
            'painel/reports/campaigns', 'painel/reports/ai', 'painel/reports/automations', 'painel/reports/social',
            'reports/exports/ai-runs', 'reports/exports/campaign-recipients',
        ];
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }
        if (preg_match('~^admin/clients/[^/]+/assign-plan$~', $path)) { return true; }
        return (bool) preg_match('~^painel/inbox/conversations/[^/]+/share-product(?:/|$)~', $path);
    }
}
