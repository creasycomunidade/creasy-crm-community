<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_scrape_jobs', function (Blueprint $table) {
            $table->string('source', 32)->default('google_places')->after('workspace_id');
            $table->unsignedSmallInteger('result_limit')->default(20)->after('radius_meters');
            $table->index(['workspace_id', 'source', 'status'], 'lead_scrape_jobs_ws_source_status_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('source_provider', 32)->default('google_places')->after('workspace_id');
            $table->string('source_id', 191)->nullable()->after('source_provider');
            $table->unique(['workspace_id', 'source_provider', 'source_id'], 'leads_workspace_source_unique');
        });

        DB::table('leads')
            ->whereNotNull('google_place_id')
            ->update([
                'source_provider' => 'google_places',
                'source_id' => DB::raw('google_place_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropUnique('leads_workspace_source_unique');
            $table->dropColumn(['source_provider', 'source_id']);
        });

        Schema::table('lead_scrape_jobs', function (Blueprint $table) {
            $table->dropIndex('lead_scrape_jobs_ws_source_status_idx');
            $table->dropColumn(['source', 'result_limit']);
        });
    }
};
