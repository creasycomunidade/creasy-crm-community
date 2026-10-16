<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('channel_account_id')->nullable();
            $table->unsignedBigInteger('contact_id');
            $table->string('external_thread_id', 128)->nullable();
            $table->enum('status', ['open', 'pending', 'resolved', 'snoozed'])->default('open');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->string('assigned_to', 16)->default('bot')
                ->comment('bot = AI handles replies, human = human agent handles replies');
            $table->timestamp('handover_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('contact_id');
            $table->index(['workspace_id', 'status']);
            $table->index('last_message_at');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->enum('direction', ['in', 'out']);
            $table->string('channel', 32);
            $table->enum('type', [
                'text', 'template', 'media', 'interactive', 'reaction',
                'image', 'video', 'document', 'audio', 'location', 'contacts',
                'sticker', 'order', 'poll', 'event', 'unsupported',
            ])->default('text');
            $table->json('payload')->nullable();
            $table->text('body')->nullable();
            $table->unsignedBigInteger('media_id')->nullable();
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->string('provider_message_id', 128)->nullable();
            $table->json('error_json')->nullable();
            $table->enum('sent_by', ['human', 'bot', 'automation', 'broadcast'])->default('human');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('conversation_id');
            $table->index('provider_message_id');
            $table->index(['conversation_id', 'sent_at']);
            $table->index(['conversation_id', 'status'], 'messages_conv_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
