<?php

namespace App\Modules\Inbox\Services;

use App\Models\User;
use App\Modules\Shared\Models\ChannelAccount;

class ChannelAccountAccess
{
    public function allows(User $user, ChannelAccount $account): bool
    {
        $workspaceId = (int) ($user->current_workspace_id ?? $user->workspace_id);
        if ((int) $account->workspace_id !== $workspaceId) {
            return false;
        }

        // Platform and client administrators retain access so a bad selection
        // can never lock the organisation out of its own connected channel.
        if ($user->isAdmin() || $user->isClientAdministrator()) {
            return true;
        }

        $operations = $account->meta_json['operations'] ?? [];

        // Staff access is always explicit. New users and channels are denied
        // until an administrator manually links the user to the channel.
        return in_array(
            (int) $user->id,
            array_map('intval', $operations['allowed_user_ids'] ?? []),
            true,
        );
    }

    /** @return array<int> */
    public function allowedAccountIds(User $user, int $workspaceId): array
    {
        return ChannelAccount::query()
            ->where('workspace_id', $workspaceId)
            ->get(['id', 'workspace_id', 'meta_json'])
            ->filter(fn (ChannelAccount $account) => $this->allows($user, $account))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
