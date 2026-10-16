<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreasyLicenseCommand extends Command
{
    protected $signature = 'creasy:license {action=status : status, heartbeat, updates ou download} {release?}';
    protected $description = 'Verifica licença e atualizações CREASY sem interromper o uso da versão instalada';

    public function handle(): int
    {
        require_once app_path('Support/Creasy/CreasyClient.php');
        $client = \Creasy\CreasyClient::fromFile((string) config('creasy.config_file'), 'respondesk', app_path('Support/Creasy/public-key.pem'), '1.0.0', strtolower((string) parse_url(config('app.url'), PHP_URL_HOST)));
        $action = $this->argument('action');
        if ($action === 'download') {
            $release = (string) $this->argument('release');
            if (!preg_match('/^[a-f0-9-]{36}$/iD', $release)) { $this->error('Informe o identificador da versão na CREASY.');return 1; }
            $directory = storage_path('app/private/creasy-updates');
            if (!is_dir($directory) && !mkdir($directory, 0700, true)) return 1;
            $result = $client->download($release, $directory.'/'.$release.'.package');
        } else {
            $result = match ($action) {
                'status' => $client->status(), 'heartbeat' => $client->heartbeat(), 'updates' => $client->updates(),
                default => ['status' => 'invalid_action'],
            };
        }
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return ($action === 'status' ? ($result['licensed'] ?? false) : ($result['status'] ?? '') === 'ok') ? 0 : 1;
    }
}
