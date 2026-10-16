<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE channel_accounts MODIFY channel ENUM('whatsapp','instagram','messenger','telegram','sms','email') NOT NULL");
        }

        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->string('external_account_id', 128)->nullable()->after('business_account_id');
        });

        DB::table('channel_accounts')->orderBy('id')->each(function ($row) {
            $meta = json_decode($row->meta_json ?? '{}', true) ?: [];
            $externalId = match ($row->channel) {
                'whatsapp' => $row->phone_number_id,
                'instagram' => $meta['instagram_account_id'] ?? $meta['instagram_page_id'] ?? null,
                'messenger' => $meta['page_id'] ?? null,
                default => null,
            };

            if ($externalId !== null && $externalId !== '') {
                DB::table('channel_accounts')->where('id', $row->id)->update([
                    'external_account_id' => (string) $externalId,
                ]);
            }
        });

        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->unique(['channel', 'external_account_id'], 'channel_accounts_channel_external_unique');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('channel_accounts', 'external_account_id')) {
            Schema::table('channel_accounts', function (Blueprint $table) {
                $table->dropUnique('channel_accounts_channel_external_unique');
                $table->dropColumn('external_account_id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE channel_accounts MODIFY channel ENUM('whatsapp','instagram','messenger','sms','email') NOT NULL");
        }
    }
};
