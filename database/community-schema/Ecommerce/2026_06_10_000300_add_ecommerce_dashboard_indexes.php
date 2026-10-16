<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            // Orders dashboard: list scoped by workspace, sorted by placed_at.
            $table->index(['workspace_id', 'placed_at'], 'ecommerce_orders_ws_placed_idx');
            // Filter by store.
            $table->index(['workspace_id', 'store_id'], 'ecommerce_orders_ws_store_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_orders', function (Blueprint $table) {
            $table->dropIndex('ecommerce_orders_ws_placed_idx');
            $table->dropIndex('ecommerce_orders_ws_store_idx');
        });
    }
};
