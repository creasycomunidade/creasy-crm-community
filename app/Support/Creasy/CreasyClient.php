<?php
declare(strict_types=1);
namespace Creasy;

/** Server-side integration. Existing releases remain usable offline. */
final class CreasyClient
{
    private array $config;
    private string $product;
    private string $publicKey;
    private $transport;

    public function __construct(array $config, string $product, string $publicKey, ?callable $transport = null)
    {
        $this->config = $config;
        $this->product = $product;
        $this->publicKey = $publicKey;
        $this->transport = $transport;
    }

    public static function fromFile(string $path, string $product, string $publicKeyFile, string $releaseVersion, ?string $runtimeDomain = null): self
    {
        $config = [];
        if ($path !== '' && is_file($path) && filesize($path) <= 32768) {
            try { $config = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR); }
            catch (\Throwable $e) { $config = []; }
        }
        $config = is_array($config) ? $config : [];
        $config['version'] = $releaseVersion; // Comes from the release adapter, not editable credentials.
        $config['_runtime_domain'] = $runtimeDomain ?? ($config['domain'] ?? '');
        return new self($config, $product, is_file($publicKeyFile) ? (string) file_get_contents($publicKeyFile) : '');
    }

    private static function decode(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) throw new \RuntimeException('Invalid encoding');
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $value) throw new \RuntimeException('Invalid encoding');
        return $decoded;
    }

    public function license(): ?array
    {
        try {
            if (!function_exists('sodium_crypto_sign_verify_detached')) return null;
            $token = $this->config['license'] ?? '';
            if (!is_string($token) || strlen($token) > 16384) return null;
            $parts = explode('.', $token);
            if (count($parts) !== 2) return null;
            $der = base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $this->publicKey), true);
            // Ed25519 SubjectPublicKeyInfo, not an arbitrary key taken from the license.
            if ($der === false || strlen($der) !== 44 || substr($der, 0, 12) !== hex2bin('302a300506032b6570032100')) return null;
            $signature = self::decode($parts[1]);
            if (strlen($signature) !== 64 || !sodium_crypto_sign_verify_detached($signature, $parts[0], substr($der, 12))) return null;
            $value = json_decode(self::decode($parts[0]), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($value) || ($value['schema'] ?? null) !== 1 || ($value['issuer'] ?? '') !== 'creasy' || ($value['scope'] ?? '') !== 'use-release') return null;
            foreach (['version', 'subject'] as $field) if (!is_string($this->config[$field] ?? null) || $this->config[$field] === '' || ($value[$field] ?? null) !== $this->config[$field]) return null;
            if (($value['product'] ?? '') !== $this->product || !self::uuid($value['licenseId'] ?? '') || !self::uuid($value['subject'])) return null;
            if (!is_string($value['termsVersion'] ?? null) || $value['termsVersion'] === '' || !preg_match('/^[a-f0-9]{64}$/D', $value['termsSha256'] ?? '') || !is_string($value['issuedAt'] ?? null) || strtotime($value['issuedAt']) === false) return null;
            $binding = $this->binding();
            if (!$binding || ($binding['licenseId'] ?? '') !== $value['licenseId'] || ($binding['subject'] ?? '') !== $value['subject'] || ($binding['product'] ?? '') !== $value['product'] || ($binding['version'] ?? '') !== $value['version']) return null;
            return $value;
        } catch (\Throwable $e) { return null; }
    }

    private static function uuid(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD', $value);
    }

    private function binding(): ?array
    {
        try {
            $token = $this->config['binding_certificate'] ?? '';
            if (!is_string($token) || strlen($token) > 16384) return null;
            $parts = explode('.', $token);if (count($parts) !== 2) return null;
            $der = base64_decode(preg_replace('/-----[A-Z ]+-----|\s/', '', $this->publicKey), true);
            if ($der === false || strlen($der) !== 44 || substr($der, 0, 12) !== hex2bin('302a300506032b6570032100')) return null;
            $signature = self::decode($parts[1]);
            if (strlen($signature) !== 64 || !sodium_crypto_sign_verify_detached($signature, $parts[0], substr($der, 12))) return null;
            $value = json_decode(self::decode($parts[0]), true, 32, JSON_THROW_ON_ERROR);
            if (($value['schema'] ?? null) !== 1 || ($value['issuer'] ?? '') !== 'creasy' || ($value['scope'] ?? '') !== 'installation-binding') return null;
            foreach (['installationId' => 'installation_id', 'deviceId' => 'device_id', 'domain' => '_runtime_domain'] as $field => $setting) {
                if (!is_string($value[$field] ?? null) || $value[$field] === '' || $value[$field] !== ($this->config[$setting] ?? null)) return null;
            }
            if (!self::uuid($value['installationId']) || !self::uuid($value['deviceId'])) return null;
            return $value;
        } catch (\Throwable $e) { return null; }
    }

    public function status(): array
    {
        return ['product' => $this->product, 'licensed' => $this->license() !== null,
            'crypto_ready' => function_exists('sodium_crypto_sign_verify_detached'),
            'version' => $this->license()['version'] ?? null,
            'domain' => $this->binding()['domain'] ?? null,
            'installation_id' => $this->binding()['installationId'] ?? null,
            'telemetry_authorized' => ($this->config['telemetry_consent'] ?? false) === true];
    }

    private function request(string $path, ?array $body = null, int $limit = 131072): array
    {
        if (!$this->license()) return ['status' => 'invalid_license'];
        if (($this->config['telemetry_consent'] ?? false) !== true) return ['status' => 'consent_required'];
        $token = $this->config['installation_token'] ?? '';
        if (!is_string($token) || !preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) return ['status' => 'unconfigured'];
        // Fixed trusted origin: credentials cannot be redirected to a configurable third party.
        $url = 'https://creasy.com.br/api/software/client/'.$path;
        try {
            if ($this->transport) {
                $r = ($this->transport)($url, $body === null ? 'GET' : 'POST', $this->headers($token), $body, $limit);
            } else {
                if (!function_exists('curl_init')) return ['status' => 'unavailable'];
                $curl = curl_init($url);$data = '';
                curl_setopt_array($curl, [CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_HTTPHEADER => $this->headers($token),
                    CURLOPT_WRITEFUNCTION => static function ($ch, string $chunk) use (&$data, $limit): int {
                        if (strlen($data) + strlen($chunk) > $limit) return 0;
                        $data .= $chunk;return strlen($chunk);
                    }]);
                if ($body !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR)]);
                $ok = curl_exec($curl);$code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);curl_close($curl);
                if ($ok === false) return ['status' => 'unavailable'];
                $r = ['code' => $code, 'body' => $data];
            }
            if (($r['code'] ?? 0) !== 200 && ($r['code'] ?? 0) !== 201) return ['status' => match ($r['code'] ?? 0) {401 => 'revoked', 403 => 'denied', 404 => 'not_available', default => 'unavailable'}];
            if (!is_string($r['body'] ?? null) || strlen($r['body']) > $limit) return ['status' => 'unavailable'];
            return ['status' => 'ok', 'body' => $r['body']];
        } catch (\Throwable $e) { return ['status' => 'unavailable']; }
    }

    public function heartbeat(): array
    {
        $license = $this->license();
        $result = $this->request('heartbeat', ['version' => $license['version'] ?? '']);
        if ($result['status'] !== 'ok') return $result;
        $value = json_decode($result['body'], true);
        if (!is_array($value) || !is_bool($value['updates_allowed'] ?? null)) return ['status' => 'unavailable'];
        // An installation token from another product or member must never unlock updates.
        if (($value['product'] ?? '') !== $this->product || ($value['subject'] ?? '') !== $license['subject'] || ($value['license_id'] ?? '') !== $license['licenseId'] || ($value['domain'] ?? '') !== $this->config['_runtime_domain'] || ($value['installation_id'] ?? '') !== $this->config['installation_id'] || ($value['device_id'] ?? '') !== $this->config['device_id']) return ['status' => 'identity_mismatch'];
        return ['status' => 'ok', 'updates_allowed' => $value['updates_allowed']];
    }

    private function headers(string $token): array
    {
        return ['Authorization: Bearer '.$token, 'Content-Type: application/json', 'Accept: application/json',
            'X-Creasy-Domain: '.$this->config['_runtime_domain'], 'X-Creasy-Installation: '.$this->config['installation_id'], 'X-Creasy-Device: '.$this->config['device_id']];
    }

    public function updates(): array
    {
        $check = $this->heartbeat();
        if ($check['status'] !== 'ok') return $check;
        if (!$check['updates_allowed']) return ['status' => 'denied'];
        $result = $this->request('updates');
        if ($result['status'] !== 'ok') return $result;
        $releases = json_decode($result['body'], true);
        if (!is_array($releases) || !array_is_list($releases) || count($releases) > 50) return ['status' => 'unavailable'];
        foreach ($releases as $release) if (!is_array($release) || !self::uuid($release['id'] ?? '') || !is_string($release['version'] ?? null) || !is_string($release['terms_version'] ?? null) || !is_string($release['changelog'] ?? null)) return ['status' => 'unavailable'];
        return ['status' => 'ok', 'releases' => $releases];
    }

    /** Downloads only. Never extracts, executes or replaces application code. */
    public function download(string $releaseId, string $destination): array
    {
        if (!self::uuid($releaseId)) return ['status' => 'invalid_release'];
        $list = $this->updates();
        if ($list['status'] !== 'ok') return $list;
        if (!in_array($releaseId, array_column($list['releases'], 'id'), true)) return ['status' => 'not_available'];
        $result = $this->request('releases/'.$releaseId.'/download', null, 32 * 1024 * 1024);
        if ($result['status'] !== 'ok') return $result;
        if ($result['body'] === '') return ['status' => 'unavailable'];
        $old = umask(0077);
        try {
            $file = @fopen($destination, 'xb');
            if (!$file) return ['status' => 'write_failed'];
            $written = fwrite($file, $result['body']);fclose($file);
            if ($written !== strlen($result['body'])) { @unlink($destination);return ['status' => 'write_failed']; }
        } finally { umask($old); }
        return ['status' => 'ok', 'bytes' => $written, 'sha256' => hash('sha256', $result['body'])];
    }
}
