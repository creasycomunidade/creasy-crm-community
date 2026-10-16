<?php

namespace App\Services;

use App\Models\Currency;

class CurrencyService
{
    /**
     * Format amount (in smallest unit, e.g. cents) for display in the given currency.
     */
    public function format(int $amountCents, string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->where('enabled', true)->first();
        if (! $currency) {
            return (string) $amountCents;
        }

        $value = $amountCents / (10 ** $currency->decimals);

        if ($currency->code === 'BRL') {
            return 'R$ '.number_format($value, $currency->decimals, ',', '.');
        }

        return $currency->symbol . number_format($value, $currency->decimals);
    }

    /**
     * Convert amount from one currency to another using stored exchange_rate.
     * Amount is in smallest unit (cents). Returns amount in smallest unit of target currency.
     * exchange_rate = units of the default currency per 1 unit of this
     * currency (e.g. with BRL as default, 1 USD = 5.06 BRL => USD.rate 5.06).
     */
    public function convert(int $amountCents, string $fromCode, string $toCode): int
    {
        if ($fromCode === $toCode) {
            return $amountCents;
        }

        $from = Currency::where('code', $fromCode)->where('enabled', true)->first();
        $to = Currency::where('code', $toCode)->where('enabled', true)->first();

        if (! $from || ! $to) {
            return $amountCents;
        }

        $fromRate = (float) $from->exchange_rate;
        $toRate = (float) $to->exchange_rate;

        $majorFrom = $amountCents / (10 ** $from->decimals);
        $inDefault = $majorFrom * $fromRate;   // to default currency
        $majorTo = $inDefault / $toRate;      // from default to target
        $centsTo = (int) round($majorTo * (10 ** $to->decimals));

        return $centsTo;
    }

    /**
     * Get display amount in target currency (converted from stored price_cents in plan's currency).
     */
    public function formatConverted(int $amountCents, string $fromCode, string $toCode): string
    {
        $converted = $this->convert($amountCents, $fromCode, $toCode);

        return $this->format($converted, $toCode);
    }
}
