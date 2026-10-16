<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->unsignedSmallInteger('buffer_minutes')->default(0);
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 8)->default('BRL');
            $table->string('color', 20)->default('#7c3aed');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['workspace_id', 'active']);
        });

        Schema::create('appointment_resources', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['workspace_id', 'active']);
        });

        Schema::create('appointment_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
            $table->unique(['resource_id', 'weekday', 'starts_at', 'ends_at'], 'appointment_availability_unique');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('resource_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('customer_name', 180);
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 32)->nullable();
            // Keep compatibility with MariaDB strict mode (no implicit zero dates).
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->string('status', 30)->default('scheduled');
            $table->text('notes')->nullable();
            $table->string('source', 40)->default('panel');
            $table->string('external_calendar_id')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'starts_at', 'status'], 'appointments_calendar_idx');
            $table->index(['resource_id', 'starts_at', 'ends_at'], 'appointments_conflict_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_availabilities');
        Schema::dropIfExists('appointment_resources');
        Schema::dropIfExists('appointment_services');
    }
};
