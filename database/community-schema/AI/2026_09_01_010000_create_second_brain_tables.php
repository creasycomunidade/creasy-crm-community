<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_memory_nodes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('node_type', 32)->default('insight');
            $table->string('title', 180);
            $table->text('summary')->nullable();
            $table->longText('content')->nullable();
            $table->json('keywords')->nullable();
            $table->string('source_type', 40)->default('manual');
            $table->string('source_id', 120)->nullable();
            $table->unsignedTinyInteger('importance')->default(3);
            $table->decimal('confidence', 4, 3)->default(1);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'node_type'], 'memory_nodes_workspace_status_type_idx');
            $table->index(['workspace_id', 'importance', 'updated_at'], 'memory_nodes_workspace_rank_idx');
            $table->unique(['workspace_id', 'source_type', 'source_id'], 'memory_nodes_workspace_source_unique');
        });

        Schema::create('ai_memory_edges', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('from_node_id');
            $table->unsignedBigInteger('to_node_id');
            $table->string('relation', 60);
            $table->decimal('weight', 4, 3)->default(0.5);
            $table->text('evidence')->nullable();
            $table->boolean('automatic')->default(false);
            $table->timestamps();

            $table->foreign('from_node_id')->references('id')->on('ai_memory_nodes')->cascadeOnDelete();
            $table->foreign('to_node_id')->references('id')->on('ai_memory_nodes')->cascadeOnDelete();
            $table->index(['workspace_id', 'relation'], 'memory_edges_workspace_relation_idx');
            $table->unique(['workspace_id', 'from_node_id', 'to_node_id', 'relation'], 'memory_edges_unique_relation');
        });

        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'modules')) {
            DB::table('plans')->select(['id', 'modules'])->orderBy('id')->get()->each(function ($plan): void {
                $modules = json_decode((string) ($plan->modules ?? '{}'), true);
                $modules = is_array($modules) ? $modules : [];
                $modules['second_brain'] = true;
                DB::table('plans')->where('id', $plan->id)->update([
                    'modules' => json_encode($modules, JSON_UNESCAPED_UNICODE),
                ]);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'modules')) {
            DB::table('plans')->select(['id', 'modules'])->orderBy('id')->get()->each(function ($plan): void {
                $modules = json_decode((string) ($plan->modules ?? '{}'), true);
                if (is_array($modules)) {
                    unset($modules['second_brain']);
                    DB::table('plans')->where('id', $plan->id)->update([
                        'modules' => json_encode($modules, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
        }
        Schema::dropIfExists('ai_memory_edges');
        Schema::dropIfExists('ai_memory_nodes');
    }
};
