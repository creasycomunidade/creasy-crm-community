<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('customer_reminder_at')->nullable()->after('external_calendar_id');
            $table->timestamp('customer_reminder_sent_at')->nullable()->after('customer_reminder_at');
            $table->timestamp('professional_reminder_at')->nullable()->after('customer_reminder_sent_at');
            $table->timestamp('professional_reminder_sent_at')->nullable()->after('professional_reminder_at');
            $table->index(['customer_reminder_at', 'customer_reminder_sent_at'], 'appointments_customer_reminder_idx');
            $table->index(['professional_reminder_at', 'professional_reminder_sent_at'], 'appointments_professional_reminder_idx');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appointments_customer_reminder_idx');
            $table->dropIndex('appointments_professional_reminder_idx');
            $table->dropColumn([
                'customer_reminder_at', 'customer_reminder_sent_at',
                'professional_reminder_at', 'professional_reminder_sent_at',
            ]);
        });
    }
};
