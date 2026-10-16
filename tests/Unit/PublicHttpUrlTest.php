<?php

namespace Tests\Unit;

use App\Support\PublicHttpUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PublicHttpUrlTest extends TestCase
{
    public function test_accepts_and_pins_a_public_ip(): void
    {
        $target = PublicHttpUrl::inspect('https://93.184.216.34/webhook');
        $options = PublicHttpUrl::requestOptions('https://93.184.216.34/webhook');

        $this->assertSame('93.184.216.34', $target['ip']);
        $this->assertSame(443, $target['port']);
        $this->assertFalse($options['allow_redirects']);
    }

    #[DataProvider('blockedUrls')]
    public function test_rejects_non_public_destinations(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        PublicHttpUrl::inspect($url);
    }

    /** @return array<string, array{string}> */
    public static function blockedUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/admin'],
            'private v4' => ['http://10.0.0.1/'],
            'link local' => ['http://169.254.169.254/latest/meta-data/'],
            'loopback v6' => ['http://[::1]/'],
            'localhost' => ['http://localhost/'],
            'internal name' => ['https://database.internal/'],
            'credentials' => ['https://user:pass@93.184.216.34/'],
            'file protocol' => ['file:///etc/passwd'],
        ];
    }
}
