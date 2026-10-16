<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_memories', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->after('agent_id');
            $table->string('scope', 20)->default('private')->after('user_id');
            $table->index(['workspace_id', 'agent_id', 'scope', 'user_id'], 'ai_agent_memory_visibility_idx');
        });

        // Memories created before user isolation belonged to the workspace. Keep
        // them available to regular business agents, but personal agents never
        // load workspace-scoped memories.
        DB::table('ai_agent_memories')->update(['scope' => 'workspace']);
    }

    public function down(): void
    {
        Schema::table('ai_agent_memories', function (Blueprint $table): void {
            $table->dropIndex('ai_agent_memory_visibility_idx');
            $table->dropColumn(['user_id', 'scope']);
        });
    }
};
