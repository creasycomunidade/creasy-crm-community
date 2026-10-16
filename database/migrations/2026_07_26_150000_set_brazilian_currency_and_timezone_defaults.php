<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('currencies')) {
            DB::table('currencies')->update(['is_default' => false]);
            DB::table('currencies')->where('code', 'BRL')->update([
                'symbol' => 'R$',
                'decimals' => 2,
                'exchange_rate' => 1,
                'is_default' => true,
                'enabled' => true,
            ]);
        }


        if (Schema::hasTable('workspaces')) {
            DB::table('workspaces')->update(['currency_code' => 'BRL']);
        }

        if (Schema::hasTable('clients')) {
            DB::table('clients')->update([
                'base_currency' => 'BRL',
                'currency_symbol' => 'R$',
                'currency_position' => 'before',
            ]);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->whereNull('display_currency')
                ->update(['display_currency' => 'BRL']);

            DB::table('users')
                ->where(function ($query) {
                    $query->whereNull('timezone')
                        ->orWhereIn('timezone', ['UTC', 'Asia/Dhaka']);
                })
                ->update(['timezone' => 'America/Sao_Paulo']);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE users ALTER COLUMN timezone SET DEFAULT 'America/Sao_Paulo'");
            }
            if (DB::getDriverName() === 'mysql') {
                DB::statement(
                    "ALTER TABLE users MODIFY timezone VARCHAR(64) NULL DEFAULT 'America/Sao_Paulo'"
                );
            }
        }
    }

    public function down(): void
    {
        // Existing monetary and timezone preferences cannot be reconstructed
        // safely. A rollback intentionally keeps the Brazilian defaults.
    }
};
