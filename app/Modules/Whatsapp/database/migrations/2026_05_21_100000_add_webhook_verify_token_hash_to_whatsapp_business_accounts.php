<?php

use App\Modules\Whatsapp\Models\WhatsappBusinessAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->string('webhook_verify_token_hash', 64)->nullable()->after('webhook_verify_token');
            $table->index('webhook_verify_token_hash', 'waba_webhook_token_hash_idx');
        });

        foreach (WhatsappBusinessAccount::cursor() as $waba) {
            $token = $waba->webhook_verify_token;
            if ($token) {
                $waba->forceFill([
                    'webhook_verify_token_hash' => WhatsappBusinessAccount::hashWebhookToken($token),
                ])->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropIndex('waba_webhook_token_hash_idx');
            $table->dropColumn('webhook_verify_token_hash');
        });
    }
};
