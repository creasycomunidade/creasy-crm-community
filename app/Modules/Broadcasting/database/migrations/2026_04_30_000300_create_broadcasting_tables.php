<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 128);
            $table->enum('channel', ['whatsapp', 'sms', 'email']);
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->enum('audience_type', ['segment', 'contact_list', 'tag', 'csv'])->default('segment');
            $table->string('audience_ref', 256)->nullable();
            $table->json('template_ref')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('schedule_at')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->enum('status', ['draft', 'queued', 'sending', 'paused', 'completed', 'failed'])->default('draft');
            $table->json('totals_json')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index(['workspace_id', 'status']);
            $table->index('schedule_at');
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('contact_id');
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])->default('queued');
            $table->string('provider_message_id', 128)->nullable();
            $table->string('tracking_token', 64)->nullable()->unique();
            $table->string('unsubscribe_token', 64)->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('failed_reason', 512)->nullable();
            $table->timestamps();

            $table->index('campaign_id');
            $table->index(['campaign_id', 'status']);
            $table->index('provider_message_id');
            $table->unique(['campaign_id', 'contact_id'], 'campaign_recipients_campaign_contact_unique');

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
        });

        Schema::create('sms_provider_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->enum('provider', ['twilio', 'nexmo', 'messagebird', 'smsbd', 'reve', 'bulksmsbd']);
            $table->text('credentials')->nullable();
            $table->string('sender_id', 64)->nullable();
            $table->boolean('default')->default(false);
            $table->timestamps();

            $table->unique(['workspace_id', 'provider']);
        });

        Schema::create('usage_meters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('metric', 64);
            $table->unsignedInteger('period');
            $table->unsignedBigInteger('value')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'metric', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_meters');
        Schema::dropIfExists('sms_provider_configs');
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
