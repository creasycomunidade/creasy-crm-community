<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fazos_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('connection_id');
            $table->string('external_id', 100);
            $table->string('name')->nullable();
            $table->string('company')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('normalized_phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('payload');
            $table->timestamp('source_updated_at')->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->unique(['workspace_id', 'connection_id', 'external_id'], 'fazos_customer_external_unique');
            $table->index(['workspace_id', 'normalized_phone'], 'fazos_customer_phone_lookup');
            $table->foreign('connection_id')->references('id')->on('workspace_integration_connections')->cascadeOnDelete();
        });

        Schema::create('fazos_service_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('connection_id');
            $table->unsignedBigInteger('fazos_customer_id')->nullable();
            $table->string('external_id', 100);
            $table->string('external_customer_id', 100)->nullable();
            $table->string('number', 100)->nullable();
            $table->string('tracking_number', 160)->nullable();
            $table->string('status', 100)->nullable();
            $table->string('title')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->string('currency', 20)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('estimated_completion_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload');
            $table->timestamp('source_updated_at')->nullable();
            $table->dateTime('synced_at');
            $table->timestamps();

            $table->unique(['workspace_id', 'connection_id', 'external_id'], 'fazos_order_external_unique');
            $table->index(['workspace_id', 'fazos_customer_id', 'status'], 'fazos_order_customer_status');
            $table->foreign('connection_id')->references('id')->on('workspace_integration_connections')->cascadeOnDelete();
            $table->foreign('fazos_customer_id')->references('id')->on('fazos_customers')->nullOnDelete();
        });

        Schema::create('fazos_contact_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('connection_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('fazos_customer_id');
            $table->string('link_type', 20)->default('manual');
            $table->string('matched_phone', 20)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedBigInteger('linked_by')->nullable();
            $table->dateTime('linked_at');
            $table->timestamps();

            $table->unique(['workspace_id', 'connection_id', 'contact_id'], 'fazos_contact_connection_unique');
            $table->index(['workspace_id', 'fazos_customer_id'], 'fazos_link_customer_lookup');
            $table->foreign('connection_id')->references('id')->on('workspace_integration_connections')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('fazos_customer_id')->references('id')->on('fazos_customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fazos_contact_links');
        Schema::dropIfExists('fazos_service_orders');
        Schema::dropIfExists('fazos_customers');
    }
};
