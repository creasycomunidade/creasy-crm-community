<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sms_provider_configs DROP CONSTRAINT IF EXISTS sms_provider_configs_provider_check');
            DB::statement("ALTER TABLE sms_provider_configs ADD CONSTRAINT sms_provider_configs_provider_check CHECK (provider IN ('twilio','nexmo','messagebird','plivo','telnyx','infobip','clicksend', 'smsbd','reve','bulksmsbd','sms_dot_bd','mimsms','fast2sms','amazon_sns'))");
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // MySQL: extend the ENUM with the new global providers (Plivo, Telnyx, Infobip, ClickSend)
        DB::statement("
            ALTER TABLE sms_provider_configs
            MODIFY COLUMN provider ENUM(
                'twilio','nexmo','messagebird','plivo','telnyx','infobip','clicksend',
                'smsbd','reve','bulksmsbd','sms_dot_bd','mimsms','fast2sms','amazon_sns'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE sms_provider_configs DROP CONSTRAINT IF EXISTS sms_provider_configs_provider_check');
            DB::statement("ALTER TABLE sms_provider_configs ADD CONSTRAINT sms_provider_configs_provider_check CHECK (provider IN ('twilio','nexmo','messagebird','smsbd','reve','bulksmsbd', 'sms_dot_bd','mimsms','fast2sms','amazon_sns'))");
            return;
        }
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE sms_provider_configs
            MODIFY COLUMN provider ENUM(
                'twilio','nexmo','messagebird','smsbd','reve','bulksmsbd',
                'sms_dot_bd','mimsms','fast2sms','amazon_sns'
            ) NOT NULL
        ");
    }
};
