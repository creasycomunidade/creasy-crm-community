<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_routines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('agent_id')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->string('kind', 50)->default('task_reminder');
            $table->string('name', 180);
            $table->string('timezone', 60)->default('America/Sao_Paulo');
            $table->string('daily_time', 5)->default('08:00');
            $table->unsignedSmallInteger('interval_minutes')->default(15);
            $table->unsignedSmallInteger('deadline_warning_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamp('next_run_at')->nullable()->index();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->json('state')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'enabled', 'next_run_at'], 'ai_routines_due_idx');
            $table->unique(
                ['workspace_id', 'agent_id', 'conversation_id', 'kind'],
                'ai_routines_agent_conversation_kind_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_routines');
    }
};
