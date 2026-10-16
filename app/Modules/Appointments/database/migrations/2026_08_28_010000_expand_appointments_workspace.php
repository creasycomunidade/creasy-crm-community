<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_resources', function (Blueprint $table) {
            $table->string('position', 160)->nullable()->after('name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->unsignedBigInteger('contact_id')->nullable()->after('phone');
            $table->unsignedBigInteger('user_id')->nullable()->after('contact_id');
        });

        Schema::create('appointment_service_resource', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('resource_id');
            $table->timestamps();
            $table->unique(['service_id', 'resource_id'], 'appointment_service_resource_unique');
            $table->index(['workspace_id', 'resource_id']);
        });

        Schema::create('appointment_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->unique();
            $table->unsignedBigInteger('channel_account_id')->nullable();
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->unsignedSmallInteger('booking_horizon_days')->default(90);
            $table->unsignedSmallInteger('slot_interval_minutes')->default(30);
            $table->boolean('send_confirmation')->default(true);
            $table->boolean('send_reminders')->default(true);
            $table->boolean('send_status_updates')->default(true);
            $table->text('confirmation_message')->nullable();
            $table->text('reminder_message')->nullable();
            $table->text('cancellation_message')->nullable();
            $table->text('reschedule_message')->nullable();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->after('uuid');
            $table->text('cancel_reason')->nullable()->after('notes');
            $table->timestamp('channel_confirmation_sent_at')->nullable()->after('professional_reminder_sent_at');
            $table->timestamp('channel_reminder_sent_at')->nullable()->after('channel_confirmation_sent_at');
            $table->timestamp('channel_status_sent_at')->nullable()->after('channel_reminder_sent_at');
            $table->unique(['workspace_id', 'reference'], 'appointments_workspace_reference_unique');
        });

        DB::table('appointments')->whereNull('reference')->orderBy('id')->eachById(function (object $appointment): void {
            DB::table('appointments')->where('id', $appointment->id)->update([
                'reference' => 'AG-'.strtoupper(Str::random(8)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique('appointments_workspace_reference_unique');
            $table->dropColumn([
                'reference', 'cancel_reason', 'channel_confirmation_sent_at',
                'channel_reminder_sent_at', 'channel_status_sent_at',
            ]);
        });
        Schema::dropIfExists('appointment_settings');
        Schema::dropIfExists('appointment_service_resource');
        Schema::table('appointment_resources', function (Blueprint $table) {
            $table->dropColumn(['position', 'phone', 'contact_id', 'user_id']);
        });
    }
};
