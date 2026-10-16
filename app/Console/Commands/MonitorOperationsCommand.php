<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\CronSetupController;
use App\Services\OperationalAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class MonitorOperationsCommand extends Command
{
    protected $signature = 'ops:monitor';

    protected $description = 'Monitor scheduler, queues, failed jobs, external backups, application errors and public availability.';

    public function handle(OperationalAlertService $alerts): int
    {
        $failed = 0;
        $check = function (string $key, bool $healthy, string $title, string $message) use ($alerts, &$failed): void {
            $alerts->check($key, $healthy, $title, $message);
            $this->line(($healthy ? 'OK   ' : 'FALHA').' '.$title);
            if (! $healthy) $failed++;
        };

        $heartbeat = $this->date(Cache::get(CronSetupController::HEARTBEAT_KEY));
        $check('scheduler', $heartbeat && $heartbeat->greaterThan(now()->subMinutes(3)), 'Atividade do agendador', 'O heartbeat do cron não é atualizado há mais de 3 minutos.');

        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $queued = (int) DB::table('jobs')->count();
            $oldest = DB::table('jobs')->min('available_at');
            $oldestMinutes = $oldest ? now()->diffInMinutes(Carbon::createFromTimestamp((int) $oldest)) : 0;
            $healthy = $queued <= (int) config('operations.queue_backlog_limit')
                && $oldestMinutes <= (int) config('operations.queue_oldest_minutes');
            $check('queue', $healthy, 'Saúde das filas', "Existem {$queued} jobs; o mais antigo aguarda há {$oldestMinutes} minuto(s).");
        }

        if (Schema::hasTable('failed_jobs')) {
            $recentFailures = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(15))->count();
            $check('failed-jobs', $recentFailures === 0, 'Execução dos jobs', "Foram registrados {$recentFailures} jobs com falha nos últimos 15 minutos.");
        }

        $backup = $this->date(Cache::get('ops:last_backup_success'));
        $backupHealthy = $backup && $backup->greaterThan(now()->subHours((int) config('operations.backup_max_age_hours')));
        $check('backup', $backupHealthy, 'Atualidade do backup externo', 'Nenhum backup externo íntegro foi confirmado dentro da janela esperada.');

        $restore = $this->date(Cache::get('ops:last_restore_test_success'));
        $check('restore-test', $restore && $restore->greaterThan(now()->subDays(8)), 'Teste integral de restauração', 'A validação de restauração do backup não foi confirmada nos últimos 8 dias.');

        $url = (string) config('operations.public_health_url');
        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
            $available = $response->successful() && $response->json('status') === 'ok';
        } catch (\Throwable) {
            $available = false;
        }
        $check('availability', $available, 'Disponibilidade pública', "A verificação pública falhou em {$url}.");

        $errorLog = collect(glob(storage_path('logs/errors*.log')) ?: [])->sort()->last();
        if ($errorLog) {
            $size = (int) filesize($errorLog);
            $previousSize = (int) Cache::get('ops:error-log-size', $size);
            Cache::put('ops:error-log-size', $size, now()->addDays(7));
            $check('application-errors', $size <= $previousSize, 'Registro de erros da aplicação', 'O arquivo seguro de erros cresceu desde a última verificação. Consulte o Sentry ou o painel administrativo.');
        }

        Cache::put('ops:last_monitor_run', now()->toIso8601String(), now()->addDay());

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return filled($value) ? Carbon::parse((string) $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
