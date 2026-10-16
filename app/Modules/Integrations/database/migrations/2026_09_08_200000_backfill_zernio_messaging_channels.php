<?php

use App\Modules\Integrations\Models\ZernioAccountMapping;
use App\Modules\Shared\Models\ChannelAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ZernioAccountMapping::query()
            ->where('status', 'connected')
            ->whereIn('platform', ['facebook', 'instagram', 'telegram', 'whatsapp'])
            ->orderBy('id')
            ->eachById(function (ZernioAccountMapping $mapping): void {
                if (! (bool) data_get($mapping->capabilities, 'messages')) {
                    return;
                }

                $channel = $mapping->platform === 'facebook' ? 'messenger' : $mapping->platform;
                $account = ChannelAccount::firstOrNew([
                    'channel' => $channel,
                    'external_account_id' => $mapping->account_id,
                ]);
                $account->workspace_id = $mapping->workspace_id;
                $account->provider = 'zernio';
                $account->display_name = $mapping->display_name ?: $mapping->username ?: ucfirst($channel);
                $account->status = 'active';
                $account->credentials = [];
                $account->meta_json = array_merge($account->meta_json ?? [], [
                    'zernio_profile_id' => $mapping->profile_id,
                    'zernio_account_id' => $mapping->account_id,
                    'zernio_platform' => $mapping->platform,
                    'managed_by' => 'zernio',
                ]);
                $account->save();
            });
    }

    public function down(): void
    {
        // Data backfill is intentionally retained on rollback. Removing these rows
        // could also remove conversations created after the migration ran.
    }
};
