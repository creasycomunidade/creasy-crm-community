<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ai_provider_configs')
            ->where('provider', 'gemini')
            ->whereIn('default_model_chat', [
                'gemini-1.0-pro',
                'gemini-1.5-pro',
                'gemini-1.5-flash',
            ])
            ->update(['default_model_chat' => 'gemini-2.5-flash']);

        DB::table('ai_provider_configs')
            ->where('provider', 'gemini')
            ->where('default_model_embed', 'text-embedding-004')
            ->update(['default_model_embed' => 'gemini-embedding-001']);
    }

    public function down(): void
    {
        // Model retirement is permanent; restoring unavailable model IDs would
        // break configured workspaces after a rollback.
    }
};
