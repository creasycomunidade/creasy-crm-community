<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-workspace store connections (credentials encrypted, mirrors sms_provider_configs).
        Schema::create('ecommerce_stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('platform', 20); // shopify | woocommerce
            $table->string('name')->nullable();
            $table->string('domain'); // *.myshopify.com or the Woo store URL
            $table->text('credentials')->nullable(); // encrypted:array
            $table->string('status', 20)->default('pending'); // pending | connected | error
            $table->json('external_meta')->nullable();
            $table->string('webhook_secret', 64)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->default('untested'); // untested | ok | fail
            $table->string('last_test_message', 512)->nullable();
            $table->timestamp('customers_synced_at')->nullable();
            $table->timestamp('orders_synced_at')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->unique(['workspace_id', 'platform', 'domain']);
        });

        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('external_order_id');
            $table->string('platform', 20);
            $table->string('number')->nullable();
            $table->string('status', 40)->nullable();
            $table->string('financial_status', 40)->nullable();
            $table->string('fulfillment_status', 40)->nullable();
            $table->string('currency', 8)->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->json('line_items')->nullable();
            $table->string('tracking_url', 512)->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_order_id']);
            $table->index(['workspace_id', 'contact_id', 'placed_at']);
        });

        Schema::create('ecommerce_carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('external_id');
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 8)->nullable();
            $table->json('line_items')->nullable();
            $table->string('recovery_url', 1024)->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamp('recovery_triggered_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_id']);
            $table->index(['workspace_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_carts');
        Schema::dropIfExists('ecommerce_orders');
        Schema::dropIfExists('ecommerce_stores');
    }
};
