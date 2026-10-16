<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('created_by');
            $table->text('body');
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->dateTime('next_send_at');
            $table->timestamp('last_sent_at')->nullable();
            $table->enum('recurrence_unit', ['once', 'minute', 'hour', 'day', 'week', 'month'])->default('once');
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->unsignedInteger('max_occurrences')->nullable();
            $table->unsignedInteger('occurrences_sent')->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->enum('status', ['scheduled', 'processing', 'paused', 'completed', 'cancelled', 'failed'])->default('scheduled');
            $table->unsignedTinyInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['status', 'next_send_at']);
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};
