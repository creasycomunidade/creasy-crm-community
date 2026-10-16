<?php

namespace App\Modules\Automation\Services;

use App\Modules\Shared\Models\ChannelAccount;

class AutomationChannelCapabilities
{
    public static function supports(ChannelAccount $account, string $type, ?array $payload = null): bool
    {
        if ($account->channel === 'whatsapp' && $account->provider === 'waha' && $type === 'interactive') {
            return data_get($payload, 'interactive.type') === 'list';
        }
        return in_array($type, self::types($account), true);
    }

    public static function types(ChannelAccount $account): array
    {
        if ($account->provider === 'zernio') {
            return ['text', 'image', 'video', 'audio', 'document'];
        }
        if ($account->channel === 'whatsapp') {
            if (in_array($account->provider, ['waha', 'evolution_go'], true)) {
                return ['text', 'image', 'video', 'audio', 'document'];
            }
            if ($account->provider === 'evolution_api') {
                return ['text'];
            }

            return ['text', 'image', 'video', 'audio', 'document', 'interactive', 'location', 'template'];
        }
        if ($account->channel === 'telegram') {
            return ['text', 'image', 'document'];
        }
        if (in_array($account->channel, ['instagram', 'messenger'], true)) {
            return ['text', 'image'];
        }

        return ['text'];
    }
}
