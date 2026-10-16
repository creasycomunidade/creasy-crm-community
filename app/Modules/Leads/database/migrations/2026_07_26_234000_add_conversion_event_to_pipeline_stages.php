<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_pipeline_stages', function (Blueprint $table) {
            $table->string('conversion_event', 40)->nullable()->after('color');
        });
        DB::table('crm_pipeline_stages')->whereIn('name', ['Ganho', 'Venda concluída'])
            ->update(['conversion_event' => 'Purchase']);
        DB::table('crm_pipeline_stages')->whereIn('name', ['Qualificado', 'Lead qualificado'])
            ->update(['conversion_event' => 'QualifiedLead']);
    }

    public function down(): void
    {
        Schema::table('crm_pipeline_stages', fn (Blueprint $table) => $table->dropColumn('conversion_event'));
    }
};
