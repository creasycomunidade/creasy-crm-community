<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        Currency::query()->update(['is_default' => false]);

        // BRL is the base currency. Foreign rates below are the Banco Central
        // PTAX sell quotation from 2026-07-24: BRL units per 1 foreign unit.
        $currencies = [
            ['code' => 'BRL', 'symbol' => 'R$', 'decimals' => 2, 'exchange_rate' => 1, 'is_default' => true, 'enabled' => true],
            ['code' => 'USD', 'symbol' => 'US$', 'decimals' => 2, 'exchange_rate' => 5.0666, 'is_default' => false, 'enabled' => true],
            ['code' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'exchange_rate' => 5.7683, 'is_default' => false, 'enabled' => true],
            ['code' => 'GBP', 'symbol' => '£', 'decimals' => 2, 'exchange_rate' => 6.7619, 'is_default' => false, 'enabled' => true],
            ['code' => 'BDT', 'symbol' => '৳', 'decimals' => 2, 'exchange_rate' => 1, 'is_default' => false, 'enabled' => false],
        ];

        foreach ($currencies as $row) {
            Currency::updateOrCreate(
                ['code' => $row['code']],
                $row
            );
        }
    }
}
