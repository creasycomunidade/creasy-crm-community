<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('store_id');
            $table->string('external_id');
            $table->string('platform', 20);
            $table->string('name');
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('inventory_quantity')->nullable();
            $table->string('status', 40)->nullable();
            $table->string('image_url', 1024)->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'external_id']);
            $table->index(['workspace_id', 'store_id']);
            $table->index(['workspace_id', 'sku']);
        });

        Schema::table('ecommerce_stores', function (Blueprint $table) {
            $table->timestamp('products_synced_at')->nullable()->after('orders_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_stores', function (Blueprint $table) {
            $table->dropColumn('products_synced_at');
        });
        Schema::dropIfExists('ecommerce_products');
    }
};
