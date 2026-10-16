<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_action_confirmations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id')->index();
            $table->unsignedBigInteger('agent_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->longText('payload');
            $table->text('summary');
            $table->string('status', 20)->default('pending');
            $table->dateTime('expires_at')->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'agent_id', 'user_id', 'status'], 'ai_action_confirmation_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_action_confirmations');
    }
};
