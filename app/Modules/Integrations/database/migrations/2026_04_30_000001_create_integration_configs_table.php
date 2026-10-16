<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('label', 128);
            $table->enum('mode', ['test', 'live'])->default('live');
            $table->boolean('enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->json('meta_json')->nullable();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->enum('last_test_status', ['ok', 'fail', 'untested'])->default('untested');
            $table->string('last_test_message', 512)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'mode']);
        });

        Schema::create('integration_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable();
            $table->unsignedBigInteger('integration_config_id')->nullable();
            $table->string('provider', 64)->nullable();
            $table->enum('action', ['create', 'update', 'delete', 'enable', 'disable', 'test', 'rotate']);
            $table->json('diff_json')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_audit_logs');
        Schema::dropIfExists('integration_configs');
    }
};
