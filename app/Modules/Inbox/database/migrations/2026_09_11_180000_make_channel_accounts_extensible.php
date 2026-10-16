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
            DB::statement('ALTER TABLE channel_accounts MODIFY channel VARCHAR(32) NOT NULL');
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE channel_accounts DROP CONSTRAINT IF EXISTS channel_accounts_channel_check');
        }

        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->string('channel', 32)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE channel_accounts MODIFY channel ENUM('whatsapp','instagram','messenger','telegram','sms','email','webchat') NOT NULL");
        }
    }
};