<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_skills', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 120);
            $table->string('slug', 150);
            $table->string('category', 50)->default('custom');
            $table->string('description', 500)->nullable();
            $table->longText('instructions');
            $table->json('trigger_terms')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('auto_activate')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'enabled', 'auto_activate']);
        });

        Schema::create('ai_agent_skill', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('skill_id');
            $table->timestamps();
            $table->primary(['agent_id', 'skill_id']);
        });

        Schema::create('ai_mcp_servers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 120);
            $table->string('endpoint_url', 2048);
            $table->string('auth_type', 30)->default('none');
            $table->longText('credentials')->nullable();
            $table->string('permission_mode', 30)->default('approval_required');
            $table->string('protocol_version', 30)->default('2025-11-25');
            $table->string('status', 30)->default('pending');
            $table->boolean('enabled')->default(true);
            $table->json('tools_cache')->nullable();
            $table->timestamp('tools_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'enabled', 'status']);
        });

        Schema::create('ai_agent_mcp_server', function (Blueprint $table): void {
            $table->unsignedBigInteger('agent_id');
            $table->unsignedBigInteger('mcp_server_id');
            $table->timestamps();
            $table->primary(['agent_id', 'mcp_server_id'], 'ai_agent_mcp_server_primary');
        });

        Schema::create('ai_mcp_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('mcp_server_id');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tool_name', 190);
            $table->json('arguments')->nullable();
            $table->string('status', 30)->default('running');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->text('result_summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'created_at']);
            $table->index(['mcp_server_id', 'tool_name']);
        });

        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'modules')) {
            DB::table('plans')->orderBy('id')->eachById(function ($plan): void {
                $modules = json_decode((string) ($plan->modules ?? ''), true);
                if (is_array($modules) && ! array_key_exists('ai_extensions', $modules)) {
                    $modules['ai_extensions'] = true;
                    DB::table('plans')->where('id', $plan->id)->update(['modules' => json_encode($modules)]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mcp_tool_calls');
        Schema::dropIfExists('ai_agent_mcp_server');
        Schema::dropIfExists('ai_mcp_servers');
        Schema::dropIfExists('ai_agent_skill');
        Schema::dropIfExists('ai_skills');
    }
};
