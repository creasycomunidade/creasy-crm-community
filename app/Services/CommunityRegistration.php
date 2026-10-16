<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CommunityRegistration
{
    public function queue(string $name, string $email, string $url): void
    {
        $path = storage_path('app/private/community-registration.json');
        if (is_file($path)) return;
        $payload = [
            'installation_id' => (string) Str::uuid(),
            'installation_secret' => bin2hex(random_bytes(32)),
            'domain' => strtolower((string) parse_url($url, PHP_URL_HOST)),
            'version' => config('community.version'),
            'admin_name' => $name,
            'admin_email' => $email,
        ];
        $this->write($path, ['pending' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), 'next_attempt' => 0]);
    }

    public function deliver(): void
    {
        $path = storage_path('app/private/community-registration.json');
        if (!is_file($path)) return;
        $lock = fopen($path.'.lock', 'c');
        if (!$lock) return;
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return; }
        try {
            $state = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (empty($state['pending']) || ($state['next_attempt'] ?? 0) > time()) return;
            // Failed deliveries never block use of the CRM or flood the collector.
            $state['next_attempt'] = time() + 3600;
            $this->write($path, $state);
            $payload = json_decode(Crypt::decryptString($state['pending']), true, flags: JSON_THROW_ON_ERROR);
            $response = Http::acceptJson()->connectTimeout(2)->timeout(4)
                ->withOptions(['allow_redirects' => false])->post(config('community.registration_url'), $payload);
            if ($response->successful() && $response->json('ok') === true) {
                // Keep only the receipt locally; discard duplicated personal data.
                $this->write($path, ['installation_id' => $payload['installation_id'], 'registered_at' => gmdate(DATE_ATOM)]);
            }
        } catch (\Throwable) {
            // Never log the payload or personal details. A later visit retries.
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function write(string $path, array $state): void
    {
        if (file_put_contents($path, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new \RuntimeException('Falha ao registrar a instalação.');
        }
        @chmod($path, 0600);
    }
}
