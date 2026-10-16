<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 120);
            $table->string('color', 7)->default('#7c3aed');
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'position']);
        });

        Schema::create('crm_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->string('name', 120);
            $table->string('color', 7)->default('#8b5cf6');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'pipeline_id', 'position'], 'crm_stages_workspace_pipeline_position_idx');
        });

        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('pipeline_id');
            $table->unsignedBigInteger('stage_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('title', 180);
            $table->unsignedBigInteger('value_cents')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['pipeline_id', 'contact_id'], 'crm_deals_pipeline_contact_unique');
            $table->index(['workspace_id', 'pipeline_id', 'stage_id', 'position'], 'crm_deals_board_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_pipeline_stages');
        Schema::dropIfExists('crm_pipelines');
    }
};
