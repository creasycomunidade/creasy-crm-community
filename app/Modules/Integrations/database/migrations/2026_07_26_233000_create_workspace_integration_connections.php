<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_integration_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('provider', 40);
            $table->string('name', 160);
            $table->text('credentials');
            $table->json('settings')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'provider', 'name'], 'workspace_integration_name_unique');
            $table->index(['workspace_id', 'provider', 'status'], 'workspace_integrations_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_integration_connections');
    }
};
