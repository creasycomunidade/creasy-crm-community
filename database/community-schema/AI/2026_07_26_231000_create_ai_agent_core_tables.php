<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->string('layer', 30);
            $table->string('description', 500);
            $table->text('persona');
            $table->text('system_prompt');
            $table->text('soul')->nullable();
            $table->json('skills')->nullable();
            $table->json('tools')->nullable();
            $table->string('permission_mode', 20)->default('read_only');
            $table->unsignedBigInteger('ai_kb_id')->nullable();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'layer', 'enabled']);
        });

        Schema::create('ai_agent_memories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('agent_id');
            $table->string('kind', 30)->default('interaction');
            $table->string('key', 120)->nullable();
            $table->text('content');
            $table->unsignedTinyInteger('importance')->default(3);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'importance', 'updated_at']);
        });

        Schema::create('ai_agent_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('input');
            $table->longText('output')->nullable();
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'agent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_runs');
        Schema::dropIfExists('ai_agent_memories');
        Schema::dropIfExists('ai_agents');
    }
};
