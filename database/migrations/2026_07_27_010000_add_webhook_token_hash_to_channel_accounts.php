<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->string('webhook_token_hash', 64)
                ->nullable()
                ->unique()
                ->after('external_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('channel_accounts', function (Blueprint $table) {
            $table->dropUnique(['webhook_token_hash']);
            $table->dropColumn('webhook_token_hash');
        });
    }
};
