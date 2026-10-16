<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_stores', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // Backfill existing rows.
        DB::table('ecommerce_stores')->whereNull('uuid')->orderBy('id')->each(function ($store) {
            DB::table('ecommerce_stores')->where('id', $store->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('ecommerce_stores', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_stores', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
