<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table): void {
            $table->longText('source_ref')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table): void {
            $table->string('source_ref', 512)->nullable()->change();
        });
    }
};
