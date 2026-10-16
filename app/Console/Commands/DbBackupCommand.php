<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

class DbBackupCommand extends Command
{
    protected $signature = 'db:backup
        {--disk=backup : Private external storage disk}
        {--no-upload : Keep backup local only}
        {--verify : Download and compare the uploaded checksum}
        {--retention-days=30 : Remove external database backups older than this many days}';

    protected $description = 'Dump the MySQL or PostgreSQL database and optionally upload to a storage disk.';

    public function handle(): int
    {
        $connection = config('database.default');

        if (! in_array($connection, ['mysql', 'pgsql'], true)) {
            $this->error("db:backup supports MySQL and PostgreSQL (configured: {$connection}).");

            return self::FAILURE;
        }

        $cfg = \Illuminate\Support\Facades\DB::connection($connection)->getConfig();
        $host = $cfg['host'];
        $port = $cfg['port'];
        $db = $cfg['database'];
        $user = $cfg['username'];
        $pass = $cfg['password'];

        $timestamp = now()->format('Y_m_d_His');
        $safeDb = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $db) ?: 'database';
        $filename = "{$safeDb}_{$timestamp}.sql.gz";
        $tmpPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.$filename;

        $this->info("Backing up database `{$db}` to {$filename}…");

        $gzip = @gzopen($tmpPath, 'wb9');
        if ($gzip === false) {
            $this->error('Could not create the temporary compressed backup file.');

            return self::FAILURE;
        }

        $stderr = '';
        $process = $connection === 'pgsql' ? new Process([
            env('BACKUP_PG_DUMP_BINARY', 'pg_dump'),
            '--host='.(string) $host, '--port='.(string) $port,
            '--username='.(string) $user, '--dbname='.(string) $db,
            '--no-owner', '--no-privileges', '--format=plain', '--no-password',
        ], null, ['PGPASSWORD' => (string) $pass, 'PGSSLMODE' => $cfg['sslmode'] ?? 'prefer']) : new Process([
            env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
            '--host='.(string) $host,
            '--port='.(string) $port,
            '--user='.(string) $user,
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            '--',
            (string) $db,
        ], null, ['MYSQL_PWD' => (string) $pass]);
        $process->setTimeout(null);

        try {
            $process->run(function (string $type, string $buffer) use ($gzip, &$stderr): void {
                if ($type === Process::OUT) {
                    gzwrite($gzip, $buffer);
                } else {
                    $stderr = substr($stderr.$buffer, -2000);
                }
            });
        } finally {
            gzclose($gzip);
        }

        if (! $process->isSuccessful() || ! file_exists($tmpPath)) {
            @unlink($tmpPath);
            $this->error('Database dump failed. Check pg_dump/mysqldump and database credentials.');
            if ($stderr !== '') {
                $this->line(trim($stderr));
            }

            return self::FAILURE;
        }

        $sizeMb = round(filesize($tmpPath) / 1_048_576, 2);
        $this->info("Dump created: {$tmpPath} ({$sizeMb} MB)");

        $hash = hash_file('sha256', $tmpPath);
        $remotePath = "backups/database/{$filename}";

        try {
            if (! $this->option('no-upload')) {
                $diskName = (string) $this->option('disk');
                $disk = Storage::disk($diskName);
                $stream = fopen($tmpPath, 'rb');
                if (! is_resource($stream) || ! $disk->writeStream($remotePath, $stream)) {
                    throw new \RuntimeException('O armazenamento externo não confirmou o upload.');
                }
                if (is_resource($stream)) fclose($stream);

                $manifest = [
                    'path' => $remotePath,
                    'created_at' => now()->toIso8601String(),
                    'bytes' => filesize($tmpPath),
                    'sha256' => $hash,
                    'database' => $safeDb,
                    'driver' => $connection,
                ];
                $disk->put($remotePath.'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                if ($this->option('verify')) {
                    $remote = $disk->readStream($remotePath);
                    if (! is_resource($remote)) throw new \RuntimeException('Não foi possível reler o backup externo.');
                    $remoteHash = hash_init('sha256');
                    hash_update_stream($remoteHash, $remote);
                    fclose($remote);
                    if (! hash_equals($hash, hash_final($remoteHash))) {
                        throw new \RuntimeException('O checksum do backup externo não confere.');
                    }
                    $this->info('Checksum externo verificado com sucesso.');
                }

                $this->prune($diskName, max(7, (int) $this->option('retention-days')));
                $this->info("Upload privado concluído: {$remotePath}");
            }

            Cache::put('ops:last_backup_success', now()->toIso8601String(), now()->addDays(90));
            Cache::put('ops:last_backup_path', $remotePath, now()->addDays(90));
            Cache::forget('ops:last_backup_error');
            $this->info('✅ Backup concluído.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            Cache::put('ops:last_backup_error', mb_substr($error->getMessage(), 0, 1000), now()->addDays(7));
            $this->error('Falha no backup externo: '.$error->getMessage());

            return self::FAILURE;
        } finally {
            if (! $this->option('no-upload')) @unlink($tmpPath);
        }
    }

    private function prune(string $diskName, int $retentionDays): void
    {
        $disk = Storage::disk($diskName);
        $cutoff = now()->subDays($retentionDays)->timestamp;
        foreach ($disk->files('backups/database') as $path) {
            if ($disk->lastModified($path) < $cutoff) {
                $disk->delete($path);
            }
        }
    }
}
