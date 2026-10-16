<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_mcp_servers', function (Blueprint $table): void {
            $table->string('connection_type', 30)->default('remote');
            $table->json('configuration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_mcp_servers', fn (Blueprint $table) => $table->dropColumn(['connection_type', 'configuration']));
    }
};
