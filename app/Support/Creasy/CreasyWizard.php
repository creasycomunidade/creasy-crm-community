<?php
declare(strict_types=1);
namespace Creasy;
require_once __DIR__.'/CreasyClient.php';

/** Called only behind the host application's administrator authentication and CSRF protection. */
final class CreasyWizard
{
    public function __construct(private string $product, private string $version, private string $baseUrl, private string $directory, private string $publicKeyFile, private ?string $configFile = null) {}

    public function domain(): string
    {
        $url = parse_url($this->baseUrl);$domain = strtolower($url['host'] ?? '');
        if (($url['scheme'] ?? '') !== 'https' || isset($url['port']) || isset($url['user']) || isset($url['pass']) || strlen($domain) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]*$/D', $domain) || preg_match('/\.(local|localhost|internal|test|invalid)$/D', $domain)) {
            throw new \RuntimeException('Configure o endereço oficial deste software com HTTPS e um domínio público antes de ativar.');
        }
        return $domain;
    }

    public function configPath(): string { return $this->configFile ?? $this->directory.'/license.json'; }

    public function status(): array
    {
        return CreasyClient::fromFile($this->configPath(), $this->product, $this->publicKeyFile, $this->version, $this->domain())->status();
    }

    public function activate(string $token, bool $accepted): void
    {
        $domain = $this->domain();
        if (!$accepted) throw new \RuntimeException('Autorize a comunicação da instalação para continuar.');
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) throw new \RuntimeException('Confira a credencial copiada da CREASY.');
        if (!function_exists('curl_init') || !function_exists('sodium_crypto_sign_verify_detached')) throw new \RuntimeException('O servidor precisa das extensões PHP cURL e Sodium para ativar a licença.');
        $oldMask = umask(0077);
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0700, true)) { umask($oldMask);throw new \RuntimeException('O servidor não conseguiu preparar o armazenamento privado.'); }
        $lock = @fopen($this->directory.'/activation.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) fclose($lock);umask($oldMask);throw new \RuntimeException('Há uma ativação em andamento. Aguarde e tente novamente.'); }
        $temporary = null;
        try {
            $identity = $this->directory.'/device-id';
            if (!is_file($identity)) {
                $bytes = random_bytes(16);$bytes[6] = chr((ord($bytes[6]) & 15) | 64);$bytes[8] = chr((ord($bytes[8]) & 63) | 128);
                $hex = bin2hex($bytes);$id = substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
                if (file_put_contents($identity, $id, LOCK_EX) === false) throw new \RuntimeException('Não foi possível salvar a identidade desta instalação.');
            }
            $device = trim((string) file_get_contents($identity));
            if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $device)) throw new \RuntimeException('Identidade local inválida. Contate a administração.');
            $curl = curl_init('https://creasy.com.br/api/software/client/activate');$data = '';
            curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$token, 'Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['domain' => $domain, 'device_id' => $device, 'product' => $this->product, 'version' => $this->version, 'accepted' => true, 'consent_version' => 'installation-domain-v2'], JSON_THROW_ON_ERROR),
                CURLOPT_WRITEFUNCTION => static function ($ch, string $part) use (&$data): int { if (strlen($data) + strlen($part) > 65536) return 0;$data .= $part;return strlen($part); }]);
            $ok = curl_exec($curl);$code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);curl_close($curl);
            if ($ok === false) throw new \RuntimeException('A CREASY não respondeu. Sua configuração anterior foi preservada. Tente novamente.');
            if ($code === 409) throw new \RuntimeException('Confira o registro TXT _creasy deste domínio e aguarde a propagação. Se a credencial já foi ativada em outro servidor, revogue-a e registre a nova instalação na CREASY.');
            if ($code !== 201 && $code !== 200) throw new \RuntimeException('Ativação recusada. Confira domínio, versão, credencial e direito ao produto na sua conta CREASY.');
            $value = json_decode($data, true, 32, JSON_THROW_ON_ERROR);
            $config = ['version' => $this->version, 'subject' => $value['subject'] ?? '', 'license' => $value['license'] ?? '', 'installation_token' => $token, 'telemetry_consent' => true,
                'installation_id' => $value['installation_id'] ?? '', 'device_id' => $device, 'domain' => $domain, '_runtime_domain' => $domain, 'binding_certificate' => $value['binding_certificate'] ?? ''];
            $client = new CreasyClient($config, $this->product, (string) file_get_contents($this->publicKeyFile));
            if (!$client->license()) throw new \RuntimeException('O certificado recebido não corresponde a este domínio e instalação. Nada foi alterado.');
            unset($config['_runtime_domain']);
            $temporary = $this->directory.'/license-'.bin2hex(random_bytes(8)).'.tmp';
            if (file_put_contents($temporary, json_encode($config, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false || !rename($temporary, $this->configPath())) throw new \RuntimeException('Não foi possível salvar a licença no armazenamento privado.');
            $temporary = null;
        } finally {
            if ($temporary && is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN);fclose($lock);umask($oldMask);
        }
    }
}
