<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_business_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('waba_id', 64)->unique();
            $table->text('credentials')->nullable();
            $table->string('webhook_verify_token', 512)->nullable()->unique();
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
        });

        Schema::create('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('waba_id_fk');
            $table->string('phone_number_id', 64)->unique();
            $table->string('display_phone', 32)->nullable();
            $table->string('verified_name', 128)->nullable();
            $table->string('quality_rating', 32)->nullable();
            $table->string('messaging_limit_tier', 64)->nullable();
            $table->string('code_verification_status')->nullable();
            $table->timestamps();

            $table->foreign('waba_id_fk')->references('id')->on('whatsapp_business_accounts')->cascadeOnDelete();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('waba_id', 64);
            $table->string('name', 128);
            $table->string('language', 8)->default('en');
            $table->enum('category', ['MARKETING', 'UTILITY', 'AUTHENTICATION'])->default('MARKETING');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED'])->default('PENDING');
            $table->json('components')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('meta_template_id', 64)->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('waba_id');
        });

        Schema::create('whatsapp_template_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 32);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('template_id')->references('id')->on('whatsapp_templates')->cascadeOnDelete();
        });

        Schema::create('whatsapp_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('channel_account_id')->nullable();
            $table->enum('trigger_type', ['keyword', 'welcome', 'away', 'out_of_hours'])->default('keyword');
            $table->enum('match_mode', ['exact', 'contains', 'regex'])->default('contains');
            $table->json('keywords')->nullable();
            $table->json('schedule_json')->nullable();
            $table->enum('response_kind', ['text', 'template', 'media', 'flow'])->default('text');
            $table->json('payload_json')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index('workspace_id');
        });

        Schema::create('whatsapp_widgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 128)->nullable();
            $table->string('widget_key', 64)->unique();
            $table->string('phone_number_id', 64)->nullable();
            $table->string('display_phone', 32)->nullable();
            $table->text('prefilled_message')->nullable();
            $table->text('greeting_message')->nullable();
            $table->string('agent_name', 64)->nullable();
            $table->string('agent_avatar_color', 16)->default('#25D366');
            $table->string('button_color', 16)->default('#25D366');
            $table->enum('position', ['bottom_right', 'bottom_left'])->default('bottom_right');
            $table->json('allowed_domains')->nullable();
            $table->json('working_hours_json')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_widgets');
        Schema::dropIfExists('whatsapp_auto_replies');
        Schema::dropIfExists('whatsapp_template_submissions');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('whatsapp_phone_numbers');
        Schema::dropIfExists('whatsapp_business_accounts');
    }
};
