<?php

namespace App\Support;

use Closure;
use InvalidArgumentException;

/**
 * Validates user-controlled outbound URLs and pins requests to a public IP.
 *
 * Redirects must remain disabled by callers. Pinning the resolved address also
 * closes the DNS-rebinding window between validation and the HTTP connection.
 */
final class PublicHttpUrl
{
    /**
     * @return array{url: string, host: string, port: int, ip: string}
     */
    public static function inspect(string $url): array
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('The URL must be an absolute HTTP or HTTPS URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only HTTP and HTTPS URLs are allowed.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Credentials in URLs are not allowed.');
        }

        $host = strtolower(rtrim(trim((string) $parts['host'], '[]'), '.'));
        if ($host === '' || strlen($host) > 253) {
            throw new InvalidArgumentException('The URL host is invalid.');
        }

        if (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        ) {
            throw new InvalidArgumentException('Local and internal hosts are not allowed.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : self::resolve($host);

        if ($ips === []) {
            throw new InvalidArgumentException('The URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new InvalidArgumentException('Private and reserved network addresses are not allowed.');
            }
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1) {
            throw new InvalidArgumentException('The URL port is invalid.');
        }

        return ['url' => $url, 'host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** @return array<string, mixed> */
    public static function requestOptions(string $url): array
    {
        $target = self::inspect($url);
        $options = ['allow_redirects' => false];

        if (defined('CURLOPT_RESOLVE')) {
            $ip = str_contains($target['ip'], ':') ? '['.$target['ip'].']' : $target['ip'];
            $options['curl'] = [
                CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$ip}"],
            ];
        }

        return $options;
    }

    public static function rule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                $fail("The {$attribute} must be a valid public URL.");

                return;
            }

            try {
                self::inspect($value);
            } catch (InvalidArgumentException $e) {
                $fail($e->getMessage());
            }
        };
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (! preg_match('/^[a-z0-9.-]+$/i', $host)) {
            return [];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[$ip] = true;
            }
        }

        return array_keys($ips);
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
