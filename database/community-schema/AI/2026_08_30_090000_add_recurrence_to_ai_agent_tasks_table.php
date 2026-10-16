<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            $table->boolean('recurrence_enabled')->default(false)->after('metadata');
            $table->string('recurrence_unit', 20)->nullable()->after('recurrence_enabled');
            $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_unit');
            $table->timestamp('next_run_at')->nullable()->after('recurrence_interval');
            $table->timestamp('recurrence_ends_at')->nullable()->after('next_run_at');
            $table->unsignedInteger('recurrence_max_runs')->nullable()->after('recurrence_ends_at');
            $table->unsignedInteger('recurrence_run_count')->default(0)->after('recurrence_max_runs');
            $table->timestamp('last_run_at')->nullable()->after('recurrence_run_count');

            $table->index(
                ['recurrence_enabled', 'next_run_at'],
                'ai_agent_tasks_recurrence_due_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('ai_agent_tasks', function (Blueprint $table) {
            $table->dropIndex('ai_agent_tasks_recurrence_due_index');
            $table->dropColumn([
                'recurrence_enabled',
                'recurrence_unit',
                'recurrence_interval',
                'next_run_at',
                'recurrence_ends_at',
                'recurrence_max_runs',
                'recurrence_run_count',
                'last_run_at',
            ]);
        });
    }
};
