<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_boards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name', 120)->default('Tarefas');
            $table->boolean('is_default')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_columns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('board_id')->index();
            $table->string('name', 120);
            $table->string('color', 7)->default('#7c3aed');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('board_id')->index();
            $table->unsignedBigInteger('column_id')->index();
            $table->unsignedBigInteger('source_message_id')->nullable()->index();
            $table->unsignedBigInteger('source_conversation_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('assigned_user_id')->nullable()->index();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('due_at')->nullable();
            $table->json('attachments')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('task_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->string('title', 255);
            $table->boolean('completed')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_checklist_items');
        Schema::dropIfExists('task_cards');
        Schema::dropIfExists('task_columns');
        Schema::dropIfExists('task_boards');
    }
};
