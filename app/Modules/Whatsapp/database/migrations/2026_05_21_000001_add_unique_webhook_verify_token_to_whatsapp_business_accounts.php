<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        // Encrypted verify tokens are base64-encoded strings; varchar(512) holds them comfortably.
        // Changing from TEXT to VARCHAR lets MySQL add a unique index without a prefix length.
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->string('webhook_verify_token', 512)->nullable()->change();
        });

        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->unique('webhook_verify_token', 'waba_unique_webhook_verify_token');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropUnique('waba_unique_webhook_verify_token');
        });

        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->text('webhook_verify_token')->nullable()->change();
        });
    }
};
