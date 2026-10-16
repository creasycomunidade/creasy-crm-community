<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('token', 64)->unique();
            // DATETIME avoids implicit zero TIMESTAMP defaults on older MySQL/MariaDB.
            $table->dateTime('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magic_links');
    }
};
