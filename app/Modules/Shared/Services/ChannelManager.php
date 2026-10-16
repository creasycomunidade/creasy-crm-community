<?php

namespace App\Modules\Shared\Services;

use App\Modules\Shared\Contracts\ChannelDriverInterface;
use InvalidArgumentException;

class ChannelManager
{
    /** @var array<string, class-string<ChannelDriverInterface>> */
    private array $drivers = [];

    public function register(string $channel, string $driverClass): void
    {
        $this->drivers[$channel] = $driverClass;
    }

    public function driver(string $channel): ChannelDriverInterface
    {
        $class = $this->drivers[$channel] ?? null;
        if (! $class) {
            throw new InvalidArgumentException("No channel driver registered for [{$channel}].");
        }

        return app($class);
    }

    public function registered(): array
    {
        return array_keys($this->drivers);
    }
}
