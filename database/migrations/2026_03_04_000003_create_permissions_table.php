<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->index('key');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
