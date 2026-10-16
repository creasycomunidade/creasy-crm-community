<?php

namespace App\Support\Concerns;

use App\Support\Demo;

/**
 * Masks personally-identifiable contact information when the application runs in
 * demo mode (config app.demo_mode). Masking happens in toArray() — the single
 * choke point through which Eloquent models flow into Inertia props, JSON
 * responses and broadcast payloads — so PII never reaches the browser, while
 * direct attribute access ($contact->phone_e164) inside the app keeps the real
 * value for internal logic.
 *
 * Implementing models declare which attributes to mask and how:
 *
 *     protected function demoMask(): array
 *     {
 *         return ['phone_e164' => 'phone', 'email' => 'email', 'first_name' => 'name'];
 *     }
 */
trait MasksDemoData
{
    /**
     * Map of serialized attribute key => mask type understood by Demo::maskValue()
     * (phone|email|name|text|array|redact|null).
     *
     * @return array<string, string>
     */
    abstract protected function demoMask(): array;

    public function toArray()
    {
        $array = parent::toArray();

        if (! Demo::active()) {
            return $array;
        }

        foreach ($this->demoMask() as $key => $type) {
            if (array_key_exists($key, $array)) {
                $array[$key] = Demo::maskValue($array[$key], $type);
            }
        }

        return $array;
    }
}
