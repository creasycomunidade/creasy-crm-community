<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('channel_accounts')) {
            return;
        }

        DB::table('channel_accounts')
            ->select(['id', 'meta_json'])
            ->orderBy('id')
            ->chunkById(200, function ($accounts): void {
                foreach ($accounts as $account) {
                    $meta = json_decode((string) ($account->meta_json ?? ''), true);
                    $meta = is_array($meta) ? $meta : [];
                    $operations = is_array($meta['operations'] ?? null) ? $meta['operations'] : [];
                    $operations['human_access_mode'] = 'selected';
                    $operations['allowed_user_ids'] = array_values(array_unique(array_map(
                        'intval',
                        is_array($operations['allowed_user_ids'] ?? null) ? $operations['allowed_user_ids'] : [],
                    )));
                    $meta['operations'] = $operations;

                    DB::table('channel_accounts')->where('id', $account->id)->update([
                        'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Access is intentionally not broadened on rollback.
    }
};
