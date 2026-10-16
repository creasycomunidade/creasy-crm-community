<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->string('name_status', 64)->nullable()->after('code_verification_status');
            $table->string('requested_verified_name', 128)->nullable()->after('name_status');
            $table->string('account_mode', 32)->nullable()->after('requested_verified_name');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropColumn(['name_status', 'requested_verified_name', 'account_mode']);
        });
    }
};
