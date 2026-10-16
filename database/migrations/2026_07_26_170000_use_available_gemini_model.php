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
                'gemini-2.5-flash',
                'gemini-2.5-flash-lite',
            ])
            ->update(['default_model_chat' => 'gemini-3.5-flash-lite']);
    }

    public function down(): void
    {
        // Do not restore model IDs unavailable to new Gemini API users.
    }
};
