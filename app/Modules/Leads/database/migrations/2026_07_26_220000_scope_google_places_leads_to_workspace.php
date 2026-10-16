<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique('leads_google_place_id_unique');
            $table->unique(['workspace_id', 'google_place_id'], 'leads_workspace_google_place_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique('leads_workspace_google_place_unique');
            $table->unique('google_place_id');
        });
    }
};
