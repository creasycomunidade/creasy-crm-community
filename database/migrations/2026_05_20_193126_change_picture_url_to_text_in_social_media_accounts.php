<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_media_accounts', function (Blueprint $table) {
            $table->text('picture_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('social_media_accounts', function (Blueprint $table) {
            $table->string('picture_url', 512)->nullable()->change();
        });
    }
};
