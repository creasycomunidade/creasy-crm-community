<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_routine_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('routine_id')->constrained('ai_agent_routines')->cascadeOnDelete();
            $t->string('status', 20);
            $t->text('summary')->nullable();
            $t->unsignedInteger('duration_ms')->default(0);
            $t->timestamps();
        });
        Schema::create('ai_library_items', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('catalog_key', 180);
            $t->string('kind', 30);
            $t->string('name', 180);
            $t->longText('content');
            $t->string('format', 10)->default('md');
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['workspace_id', 'catalog_key']);
        });
        Schema::create('ai_missions', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('title', 180);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active');
            $t->date('due_at')->nullable();
            $t->timestamps();
        });
        Schema::create('ai_projects', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('mission_id')->nullable()->constrained('ai_missions')->nullOnDelete();
            $t->string('title', 180);
            $t->text('description')->nullable();
            $t->string('status', 20)->default('active');
            $t->date('due_at')->nullable();
            $t->timestamps();
        });
        Schema::table('ai_goals', fn (Blueprint $t) => $t->foreignId('project_id')->nullable()->constrained('ai_projects')->nullOnDelete());
        Schema::table('ai_heartbeats', function (Blueprint $t) {
            $t->string('execution_mode', 20)->default('heartbeat');
            $t->string('action_mode', 20)->default('analyze');
            $t->uuid('connection_uuid')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_heartbeats', fn (Blueprint $t) => $t->dropColumn(['execution_mode', 'action_mode', 'connection_uuid']));
        Schema::table('ai_goals', fn (Blueprint $t) => $t->dropConstrainedForeignId('project_id'));
        Schema::dropIfExists('ai_routine_runs');
        Schema::dropIfExists('ai_projects');
        Schema::dropIfExists('ai_missions');
        Schema::dropIfExists('ai_library_items');
    }
};
