<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_routines', function (Blueprint $table): void {
            $table->dropUnique('ai_routines_agent_conversation_kind_unique');
            $table->string('schedule_type', 20)->default('daily')->after('name');
            $table->text('message')->nullable()->after('schedule_type');
            $table->timestamp('scheduled_for')->nullable()->after('daily_time');
            $table->timestamp('ends_at')->nullable()->after('scheduled_for');
            $table->unsignedInteger('max_runs')->nullable()->after('ends_at');
            $table->unsignedInteger('run_count')->default(0)->after('max_runs');
            $table->index(['workspace_id', 'agent_id', 'kind'], 'ai_routines_workspace_agent_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_routines', function (Blueprint $table): void {
            $table->dropIndex('ai_routines_workspace_agent_kind_idx');
            $table->dropColumn(['schedule_type', 'message', 'scheduled_for', 'ends_at', 'max_runs', 'run_count']);
            $table->unique(
                ['workspace_id', 'agent_id', 'conversation_id', 'kind'],
                'ai_routines_agent_conversation_kind_unique'
            );
        });
    }
};
