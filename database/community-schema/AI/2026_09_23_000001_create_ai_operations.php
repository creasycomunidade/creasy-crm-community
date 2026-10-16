<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', fn (Blueprint $t) => $t->json('execution_settings')->nullable());
        Schema::create('ai_goals', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('title', 180);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active');
            $t->string('metric', 20)->default('manual');
            $t->decimal('target_value', 15, 2)->default(100);
            $t->decimal('current_value', 15, 2)->default(0);
            $t->string('unit', 40)->default('%');
            $t->timestamp('due_at')->nullable();
            $t->timestamps();
            $t->index(['workspace_id', 'status']);
        });
        Schema::table('ai_agent_tasks', fn (Blueprint $t) => $t->foreignId('goal_id')->nullable()->constrained('ai_goals')->nullOnDelete());
        Schema::create('ai_heartbeats', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $t->foreignId('goal_id')->nullable()->constrained('ai_goals')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name', 180);
            $t->text('instructions');
            $t->boolean('enabled')->default(false);
            $t->unsignedInteger('interval_minutes')->default(60);
            $t->string('timezone', 80)->default('America/Sao_Paulo');
            $t->string('active_from', 5)->nullable();
            $t->string('active_until', 5)->nullable();
            $t->json('weekdays')->nullable();
            $t->unsignedInteger('daily_run_limit')->default(12);
            $t->unsignedInteger('max_output_tokens')->default(1024);
            $t->unsignedInteger('failure_count')->default(0);
            $t->string('notification_policy', 20)->default('changes');
            $t->string('last_fingerprint', 64)->nullable();
            $t->string('last_status', 30)->nullable();
            $t->text('last_summary')->nullable();
            $t->timestamp('next_run_at')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->uuid('lease_token')->nullable();
            $t->timestamps();
            $t->index(['enabled', 'next_run_at']);
            $t->index(['workspace_id', 'enabled']);
        });
        Schema::create('ai_heartbeat_runs', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('heartbeat_id')->constrained('ai_heartbeats')->cascadeOnDelete();
            $t->string('status', 30)->default('running');
            $t->string('trigger', 20)->default('scheduled');
            $t->text('summary')->nullable();
            $t->string('model')->nullable();
            $t->unsignedInteger('input_tokens')->default(0);
            $t->unsignedInteger('output_tokens')->default(0);
            $t->unsignedInteger('latency_ms')->default(0);
            $t->boolean('attention')->default(false);
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();
            $t->index(['heartbeat_id', 'created_at']);
            $t->index(['workspace_id', 'attention']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_heartbeat_runs');
        Schema::dropIfExists('ai_heartbeats');
        Schema::table('ai_agent_tasks', fn (Blueprint $t) => $t->dropConstrainedForeignId('goal_id'));
        Schema::dropIfExists('ai_goals');
        Schema::table('ai_agents', fn (Blueprint $t) => $t->dropColumn('execution_settings'));
    }
};
