<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('backlog');
            $table->string('priority', 20)->default('normal');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->longText('result_summary')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'updated_at']);
            $table->index(['workspace_id', 'agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_tasks');
    }
};
