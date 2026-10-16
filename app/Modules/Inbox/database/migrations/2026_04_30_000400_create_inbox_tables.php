<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('assigned_at');
            $table->unsignedBigInteger('assigned_by')->nullable();

            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('inbox_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index('conversation_id');
        });

        Schema::create('inbox_canned_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('shortcut', 64);
            $table->text('body');
            $table->timestamps();

            $table->unique(['workspace_id', 'shortcut']);
        });

        Schema::create('inbox_labels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 64);
            $table->string('color', 16)->default('#6366f1');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('inbox_label_conversation', function (Blueprint $table) {
            $table->unsignedBigInteger('label_id');
            $table->unsignedBigInteger('conversation_id');
            $table->primary(['label_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_label_conversation');
        Schema::dropIfExists('inbox_labels');
        Schema::dropIfExists('inbox_canned_replies');
        Schema::dropIfExists('inbox_notes');
        Schema::dropIfExists('inbox_assignments');
    }
};
