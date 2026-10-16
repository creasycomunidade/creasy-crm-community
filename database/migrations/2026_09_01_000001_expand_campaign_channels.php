<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('campaigns', 'channel_account_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->unsignedBigInteger('channel_account_id')->nullable()->after('channel');
                $table->index(['workspace_id', 'channel_account_id'], 'campaigns_workspace_channel_account_index');
            });
        }

        // The original column was an enum containing only WhatsApp, SMS and e-mail.
        // A VARCHAR keeps future channel drivers extensible without another table rebuild.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE campaigns MODIFY channel VARCHAR(32) NOT NULL');
        } elseif (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE campaigns DROP CONSTRAINT IF EXISTS campaigns_channel_check');
            DB::statement('ALTER TABLE campaigns ALTER COLUMN channel TYPE VARCHAR(32)');
        } elseif (DB::getDriverName() === 'sqlite') {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->string('channel', 32)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'channel_account_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropIndex('campaigns_workspace_channel_account_index');
                $table->dropColumn('channel_account_id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE campaigns MODIFY channel ENUM('whatsapp','sms','email') NOT NULL");
        }
    }
};
