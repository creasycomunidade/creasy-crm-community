<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('flag', 20)->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_rtl')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
