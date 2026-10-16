<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->string('media_path')->nullable()->after('body');
            $table->text('media_url')->nullable()->after('media_path');
            $table->string('media_mime', 100)->nullable()->after('media_url');
            $table->string('media_name')->nullable()->after('media_mime');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_messages', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_url', 'media_mime', 'media_name']);
        });
    }
};
