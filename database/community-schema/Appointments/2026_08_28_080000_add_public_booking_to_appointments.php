<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_resources', function (Blueprint $table) {
            $table->boolean('booking_enabled')->default(true)->after('active');
        });

        Schema::table('appointment_settings', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('workspace_id');
        });

        DB::table('appointment_settings')->whereNull('public_token')->orderBy('id')->eachById(function (object $setting): void {
            DB::table('appointment_settings')->where('id', $setting->id)->update([
                'public_token' => Str::random(48),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('appointment_settings', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });

        Schema::table('appointment_resources', function (Blueprint $table) {
            $table->dropColumn('booking_enabled');
        });
    }
};
