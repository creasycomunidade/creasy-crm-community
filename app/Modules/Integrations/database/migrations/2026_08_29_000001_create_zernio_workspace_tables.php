<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zernio_workspace_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->unique();
            $table->string('profile_id', 64)->unique();
            $table->string('profile_name', 180);
            $table->string('status', 24)->default('connected');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        Schema::create('zernio_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('connection_id');
            $table->string('profile_id', 64);
            $table->string('account_id', 64)->unique();
            $table->string('platform', 40);
            $table->string('username', 180)->nullable();
            $table->string('display_name', 255)->nullable();
            $table->string('status', 24)->default('connected');
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'platform']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('connection_id')->references('id')->on('zernio_workspace_connections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zernio_account_mappings');
        Schema::dropIfExists('zernio_workspace_connections');
    }
};
