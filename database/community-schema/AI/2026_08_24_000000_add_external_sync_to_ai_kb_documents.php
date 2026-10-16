<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_integration_id')->nullable()->after('kb_id');
            $table->string('external_provider', 40)->nullable()->after('source_type');
            $table->json('source_metadata')->nullable()->after('source_ref');
            $table->string('source_version', 191)->nullable()->after('source_metadata');
            $table->boolean('sync_enabled')->default(false)->after('source_version');
            $table->timestamp('last_synced_at')->nullable()->after('last_indexed_at');
            $table->text('sync_error')->nullable()->after('last_synced_at');

            $table->index(
                ['external_provider', 'sync_enabled', 'last_synced_at'],
                'ai_kb_docs_external_sync_idx'
            );
            $table->index('workspace_integration_id', 'ai_kb_docs_integration_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_kb_documents', function (Blueprint $table) {
            $table->dropIndex('ai_kb_docs_external_sync_idx');
            $table->dropIndex('ai_kb_docs_integration_idx');
            $table->dropColumn([
                'workspace_integration_id',
                'external_provider',
                'source_metadata',
                'source_version',
                'sync_enabled',
                'last_synced_at',
                'sync_error',
            ]);
        });
    }
};
