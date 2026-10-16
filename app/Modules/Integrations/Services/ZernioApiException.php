<?php

namespace App\Modules\Integrations\Services;

use RuntimeException;

class ZernioApiException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly array $payload, string $message)
    {
        parent::__construct($message);
    }
}
