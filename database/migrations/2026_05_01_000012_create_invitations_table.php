<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email');
            $table->string('role')->nullable();
            $table->string('client_role')->nullable();
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            // DATETIME avoids implicit zero TIMESTAMP defaults on older MySQL/MariaDB.
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['token']);
            $table->index(['email', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
