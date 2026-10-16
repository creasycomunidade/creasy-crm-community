<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('provider', 20);
            $table->string('name');
            $table->string('account_id', 128);
            $table->string('dataset_id', 128)->nullable();
            $table->text('credentials');
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'provider', 'account_id']);
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('ad_campaign_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('connection_id');
            $table->string('external_id', 128);
            $table->string('provider', 20);
            $table->string('name');
            $table->string('status', 40)->nullable();
            $table->string('objective', 80)->nullable();
            $table->decimal('spend', 14, 2)->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('leads')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('conversion_value', 14, 2)->default(0);
            $table->string('currency', 8)->default('BRL');
            $table->json('raw')->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();
            $table->unique(['connection_id', 'external_id']);
            $table->index(['workspace_id', 'provider']);
        });

        Schema::create('tracking_sites', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_key')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name');
            $table->json('allowed_domains');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->index('workspace_id');
        });

        Schema::create('attribution_touches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('tracking_site_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->uuid('visitor_id');
            $table->string('event', 40)->default('page_view');
            $table->string('source', 100)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('campaign', 180)->nullable();
            $table->string('content', 180)->nullable();
            $table->string('term', 180)->nullable();
            $table->string('gclid', 255)->nullable();
            $table->string('gbraid', 255)->nullable();
            $table->string('wbraid', 255)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->text('landing_url')->nullable();
            $table->text('referrer')->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();
            $table->index(['workspace_id', 'visitor_id', 'occurred_at'], 'attribution_visitor_idx');
            $table->index(['workspace_id', 'contact_id', 'occurred_at'], 'attribution_contact_idx');
        });

        Schema::create('ad_conversion_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->string('provider', 20);
            $table->string('event_name', 80);
            $table->dateTime('event_time');
            $table->decimal('value', 14, 2)->default(0);
            $table->string('currency', 8)->default('BRL');
            $table->string('gclid', 255)->nullable();
            $table->string('gbraid', 255)->nullable();
            $table->string('wbraid', 255)->nullable();
            $table->string('fbclid', 255)->nullable();
            $table->text('match_data')->nullable();
            $table->string('dedup_key', 191)->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('provider_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'provider', 'status'], 'ad_conversions_dispatch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_conversion_events');
        Schema::dropIfExists('attribution_touches');
        Schema::dropIfExists('tracking_sites');
        Schema::dropIfExists('ad_campaign_snapshots');
        Schema::dropIfExists('ad_connections');
    }
};
