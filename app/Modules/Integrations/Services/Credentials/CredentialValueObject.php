<?php

namespace App\Modules\Integrations\Services\Credentials;

abstract class CredentialValueObject
{
    public function __construct(protected array $data) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return ! empty($this->data[$key]);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
