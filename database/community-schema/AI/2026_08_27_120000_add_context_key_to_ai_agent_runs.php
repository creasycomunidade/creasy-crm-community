<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_runs', function (Blueprint $table): void {
            $table->string('context_key', 100)->nullable()->after('user_id');
            $table->index(['workspace_id', 'agent_id', 'user_id', 'context_key'], 'ai_agent_runs_context_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_runs', function (Blueprint $table): void {
            $table->dropIndex('ai_agent_runs_context_idx');
            $table->dropColumn('context_key');
        });
    }
};
