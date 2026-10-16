<?php

namespace App\Support;

/**
 * Demo mode helpers.
 *
 * When APP_DEMO_MODE=true (config/app.php → app.demo_mode) the application runs
 * as a public, read-only showcase: every write is blocked by EnsureNotDemoMode
 * and all end-customer contact information (names, phone numbers, emails,
 * addresses, free-text PII) is masked before it ever reaches the browser.
 *
 * The static maskXxx() methods always mask; the convenience phone()/email()/
 * name()/text() helpers only mask when demo mode is active, so call sites can
 * use them unconditionally:
 *
 *     'email' => Demo::email($contact->email),
 */
class Demo
{
    /** Character used to obscure masked portions of a value. */
    private const DOT = '•';

    public static function active(): bool
    {
        return (bool) config('app.demo_mode', false);
    }

    // ─── Conditional helpers (no-op outside demo mode) ───────────────────────

    public static function phone(?string $value): ?string
    {
        return self::active() ? self::maskPhone($value) : $value;
    }

    public static function email(?string $value): ?string
    {
        return self::active() ? self::maskEmail($value) : $value;
    }

    public static function name(?string $value): ?string
    {
        return self::active() ? self::maskName($value) : $value;
    }

    /** Scrub emails and phone-like sequences out of free text (message bodies). */
    public static function text(?string $value): ?string
    {
        return self::active() ? self::maskText($value) : $value;
    }

    // ─── Raw maskers (always mask — used by the model trait) ──────────────────

    public static function maskPhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $plus = str_starts_with(trim($value), '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $len = strlen($digits);

        if ($len <= 4) {
            return $plus.str_repeat(self::DOT, max($len, 2));
        }

        return $plus.substr($digits, 0, 2).str_repeat(self::DOT, $len - 4).substr($digits, -2);
    }

    public static function maskEmail(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! str_contains($value, '@')) {
            return self::maskName($value);
        }

        [$local, $domain] = explode('@', $value, 2);

        $maskedLocal = strlen($local) <= 2
            ? substr($local, 0, 1).self::DOT
            : substr($local, 0, 2).str_repeat(self::DOT, max(1, strlen($local) - 2));

        $dot = strrpos($domain, '.');
        $tld = $dot !== false ? substr($domain, $dot) : '';

        return $maskedLocal.'@'.str_repeat(self::DOT, 3).$tld;
    }

    public static function maskName(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return $value;
        }

        $parts = preg_split('/\s+/', trim($value)) ?: [];
        $masked = array_map(function (string $part): string {
            $len = mb_strlen($part);

            return $len <= 1
                ? self::DOT
                : mb_substr($part, 0, 1).str_repeat(self::DOT, $len - 1);
        }, $parts);

        return implode(' ', $masked);
    }

    public static function maskText(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Emails first so their digits aren't caught by the phone pattern.
        $value = preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            str_repeat(self::DOT, 3).'@'.str_repeat(self::DOT, 3),
            $value
        ) ?? $value;

        // Phone-like runs: optional + then 7+ digits possibly broken by spaces/dashes.
        $value = preg_replace_callback(
            '/\+?\d[\d\s\-().]{6,}\d/',
            fn (array $m): string => self::maskPhone($m[0]),
            $value
        ) ?? $value;

        return $value;
    }

    /** Redact every scalar value in an array (e.g. Contact custom_fields). */
    public static function maskArrayValues(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_map(function ($v) {
            if (is_array($v)) {
                return self::maskArrayValues($v);
            }
            if ($v === null || $v === '' || is_bool($v)) {
                return $v;
            }

            return str_repeat(self::DOT, 6);
        }, $value);
    }

    /**
     * Dispatch masking by type. Used by the MasksDemoData trait.
     *
     * Supported types: phone, email, name, text, array, redact, null.
     */
    public static function maskValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'phone' => self::maskPhone((string) $value),
            'email' => self::maskEmail((string) $value),
            'name' => self::maskName((string) $value),
            'text' => self::maskText((string) $value),
            'array' => self::maskArrayValues($value),
            'null' => null,
            'redact' => is_scalar($value) ? str_repeat(self::DOT, 6) : $value,
            default => $value,
        };
    }
}
