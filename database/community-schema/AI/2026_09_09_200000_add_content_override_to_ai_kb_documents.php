<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table): void {
            $table->longText('content_override')->nullable()->after('source_ref');
            $table->timestamp('content_override_updated_at')->nullable()->after('content_override');
        });
    }

    public function down(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table): void {
            $table->dropColumn(['content_override', 'content_override_updated_at']);
        });
    }
};
