<?php

namespace App\Modules\AI\Services;

use App\Modules\Broadcasting\Models\UsageMeter;

class PlatformAiLimit
{
    public function assertAvailable(int $workspaceId): void
    {
        // Self-hosted Community has no subscription token allowance.
    }

    public function record(int $workspaceId, int $tokens): void
    {
        if ($tokens > 0) {
            UsageMeter::track($workspaceId, 'platform_ai_tokens', $tokens);
        }
    }
}
