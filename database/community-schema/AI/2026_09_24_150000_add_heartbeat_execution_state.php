<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_heartbeats', fn (Blueprint $table) => $table->json('execution_state')->nullable());
        Schema::table('ai_heartbeat_runs', fn (Blueprint $table) => $table->json('execution_log')->nullable());
    }

    public function down(): void
    {
        Schema::table('ai_heartbeat_runs', fn (Blueprint $table) => $table->dropColumn('execution_log'));
        Schema::table('ai_heartbeats', fn (Blueprint $table) => $table->dropColumn('execution_state'));
    }
};
