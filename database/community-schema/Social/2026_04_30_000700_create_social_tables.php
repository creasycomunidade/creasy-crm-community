<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('social_media_post_accounts');
        Schema::dropIfExists('social_media_posts');
        Schema::dropIfExists('social_media_accounts');

        Schema::create('social_media_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->enum('network', ['facebook', 'instagram', 'linkedin', 'twitter', 'youtube', 'tiktok']);
            $table->string('account_id', 128);
            $table->string('name', 256)->nullable();
            $table->string('picture_url', 512)->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'network', 'account_id']);
            $table->index('workspace_id');
        });

        Schema::create('social_media_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('title', 256)->nullable();
            $table->text('body')->nullable();
            $table->json('media_urls')->nullable();
            $table->json('target_accounts')->nullable();
            $table->enum('status', ['draft', 'scheduled', 'publishing', 'published', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('provider_post_id')->nullable();
            $table->string('post_url')->nullable();
            $table->json('publish_results')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->text('ai_prompt')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('scheduled_at');
            $table->index(['status', 'scheduled_at'], 'social_posts_status_scheduled_at_idx');
        });

        Schema::create('social_media_post_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('social_account_id');
            $table->enum('status', ['pending', 'published', 'failed'])->default('pending');
            $table->string('platform_post_id', 256)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['post_id', 'social_account_id']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_post_accounts');
        Schema::dropIfExists('social_media_posts');
        Schema::dropIfExists('social_media_accounts');
    }
};
