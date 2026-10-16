<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_settings', function (Blueprint $table): void {
            $table->boolean('google_calendar_enabled')->default(false);
            $table->boolean('google_calendar_invite_customer')->default(true);
            $table->string('google_calendar_id')->default('primary');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->text('external_calendar_link')->nullable();
            $table->timestamp('external_calendar_synced_at')->nullable();
            $table->text('external_calendar_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('appointment_settings', function (Blueprint $table): void {
            $table->dropColumn(['google_calendar_enabled', 'google_calendar_invite_customer', 'google_calendar_id']);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn(['external_calendar_link', 'external_calendar_synced_at', 'external_calendar_error']);
        });
    }
};
