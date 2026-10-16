<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_mind_maps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('last_edited_by')->nullable();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('theme', 32)->default('aurora');
            $table->json('nodes');
            $table->json('edges')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'updated_at']);
        });

        if (Schema::hasTable('plans') && Schema::hasColumn('plans', 'modules')) {
            DB::table('plans')->select(['id', 'modules'])->orderBy('id')->get()->each(function ($plan): void {
                $modules = json_decode((string) ($plan->modules ?? '{}'), true);
                $modules = is_array($modules) ? $modules : [];
                $modules['mind_maps'] = true;
                DB::table('plans')->where('id', $plan->id)->update(['modules' => json_encode($modules, JSON_UNESCAPED_UNICODE)]);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_mind_maps');
    }
};
