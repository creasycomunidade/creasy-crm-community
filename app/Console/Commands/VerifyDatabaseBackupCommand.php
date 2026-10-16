<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

class VerifyDatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup-verify
        {--disk=backup : External backup disk}
        {--path= : Exact .sql.gz object}
        {--full : Import into the dedicated BACKUP_RESTORE_DATABASE and query the restored schema}';

    protected $description = 'Download the latest external database backup, verify its checksum and validate that it can be decompressed as SQL.';

    public function handle(): int
    {
        try {
            $disk = Storage::disk((string) $this->option('disk'));
            $path = (string) ($this->option('path') ?: collect($disk->files('backups/database'))
                ->filter(fn (string $item) => str_ends_with($item, '.sql.gz'))
                ->sortByDesc(fn (string $item) => $disk->lastModified($item))
                ->first());
            if ($path === '') throw new \RuntimeException('Nenhum backup externo foi encontrado.');

            $manifest = json_decode((string) $disk->get($path.'.json'), true);
            if (! is_array($manifest) || empty($manifest['sha256'])) {
                throw new \RuntimeException('Manifesto de integridade ausente.');
            }

            $remote = $disk->readStream($path);
            if (! is_resource($remote)) throw new \RuntimeException('Não foi possível baixar o backup.');
            $tmp = tempnam(sys_get_temp_dir(), 'restore-check-');
            $local = fopen($tmp, 'wb');
            stream_copy_to_stream($remote, $local);
            fclose($remote);
            fclose($local);

            try {
                if (! hash_equals((string) $manifest['sha256'], hash_file('sha256', $tmp))) {
                    throw new \RuntimeException('Checksum inválido após o download.');
                }
                $gzip = gzopen($tmp, 'rb');
                if ($gzip === false) throw new \RuntimeException('O arquivo não pôde ser descompactado.');
                $sample = '';
                while (! gzeof($gzip) && strlen($sample) < 2_000_000) {
                    $sample .= (string) gzread($gzip, 65536);
                }
                gzclose($gzip);
                if (! str_contains($sample, 'CREATE TABLE') && ! str_contains($sample, 'INSERT INTO')) {
                    throw new \RuntimeException('O conteúdo descompactado não contém uma estrutura SQL restaurável.');
                }
                if ($this->option('full')) {
                    if (($manifest['driver'] ?? 'mysql') !== config('database.default')) {
                        throw new \RuntimeException('O backup pertence a outro tipo de banco.');
                    }
                    $this->fullRestore($tmp);
                }
            } finally {
                @unlink($tmp);
            }

            Cache::put('ops:last_backup_validation_success', now()->toIso8601String(), now()->addDays(90));
            if ($this->option('full')) {
                Cache::put('ops:last_restore_test_success', now()->toIso8601String(), now()->addDays(90));
                Cache::forget('ops:last_restore_test_error');
            }
            $this->info("✅ Backup validado para restauração: {$path}");

            return self::SUCCESS;
        } catch (\Throwable $error) {
            if ($this->option('full')) {
                Cache::put('ops:last_restore_test_error', mb_substr($error->getMessage(), 0, 1000), now()->addDays(7));
            }
            $this->error('Falha no teste de restauração: '.$error->getMessage());

            return self::FAILURE;
        }
    }

    private function fullRestore(string $compressedPath): void
    {
        if (config('database.default') === 'pgsql') {
            $this->restorePostgres($compressedPath);
            return;
        }
        $source = (array) config('database.connections.mysql');
        $sourceDatabase = (string) ($source['database'] ?? '');
        $restoreDatabase = (string) config('operations.restore_database');

        if ($restoreDatabase === '') {
            throw new \RuntimeException('Defina BACKUP_RESTORE_DATABASE com um banco MySQL vazio e exclusivo para o teste.');
        }
        if ($sourceDatabase === '' || hash_equals($sourceDatabase, $restoreDatabase)) {
            throw new \RuntimeException('O banco de restauração não pode ser o banco de produção.');
        }
        if (! preg_match('/^[A-Za-z0-9_]+$/', $restoreDatabase)) {
            throw new \RuntimeException('BACKUP_RESTORE_DATABASE contém caracteres inválidos.');
        }

        $input = new InputStream;
        $mysql = new Process([
            env('BACKUP_MYSQL_BINARY', 'mysql'),
            '--host='.(string) ($source['host'] ?? '127.0.0.1'),
            '--port='.(string) ($source['port'] ?? 3306),
            '--user='.(string) ($source['username'] ?? ''),
            '--database='.$restoreDatabase,
            '--default-character-set=utf8mb4',
        ], null, ['MYSQL_PWD' => (string) ($source['password'] ?? '')], $input);
        $mysql->setTimeout(null);

        $gzip = gzopen($compressedPath, 'rb');
        if ($gzip === false) {
            throw new \RuntimeException('Não foi possível abrir o backup para a restauração integral.');
        }

        $mysql->start();
        try {
            while (! gzeof($gzip)) {
                $chunk = gzread($gzip, 65536);
                if ($chunk !== false && $chunk !== '') {
                    $input->write($chunk);
                }
            }
            $input->close();
            $mysql->wait();
        } finally {
            gzclose($gzip);
        }

        if (! $mysql->isSuccessful()) {
            throw new \RuntimeException('O MySQL recusou a restauração no banco de teste: '.mb_substr(trim($mysql->getErrorOutput()), 0, 500));
        }

        $verification = new Process([
            env('BACKUP_MYSQL_BINARY', 'mysql'),
            '--host='.(string) ($source['host'] ?? '127.0.0.1'),
            '--port='.(string) ($source['port'] ?? 3306),
            '--user='.(string) ($source['username'] ?? ''),
            '--database='.$restoreDatabase,
            '--batch',
            '--skip-column-names',
            '--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()',
        ], null, ['MYSQL_PWD' => (string) ($source['password'] ?? '')]);
        $verification->mustRun();
        if ((int) trim($verification->getOutput()) < 1) {
            throw new \RuntimeException('A restauração terminou sem tabelas no banco de teste.');
        }

        $this->info("Restauração integral confirmada no banco isolado `{$restoreDatabase}`.");
    }

    private function restorePostgres(string $compressedPath): void
    {
        $source = \Illuminate\Support\Facades\DB::connection()->getConfig();
        $database = (string) config('operations.restore_database');
        if ($database === '' || $database === ($source['database'] ?? '') || ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new \RuntimeException('Defina BACKUP_RESTORE_DATABASE como um banco vazio, exclusivo e diferente do banco em uso.');
        }
        $args = [env('BACKUP_PSQL_BINARY', 'psql'), '--no-psqlrc', '--no-password',
            '--host='.(string) $source['host'], '--port='.(string) $source['port'],
            '--username='.(string) $source['username'], '--dbname='.$database, '--set=ON_ERROR_STOP=1'];
        $environment = ['PGPASSWORD' => (string) ($source['password'] ?? ''), 'PGSSLMODE' => $source['sslmode'] ?? 'prefer'];
        $countArgs = array_merge($args, ['--tuples-only', '--no-align', "--command=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema NOT IN ('pg_catalog', 'information_schema')"]);
        $check = new Process($countArgs, null, $environment);
        $check->mustRun();
        if ((int) trim($check->getOutput()) !== 0) {
            throw new \RuntimeException('O banco de teste de restauração precisa estar vazio.');
        }
        $gzip = gzopen($compressedPath, 'rb');
        if ($gzip === false) throw new \RuntimeException('Não foi possível abrir o backup.');
        $stream = (function () use ($gzip) {
            while (! gzeof($gzip)) {
                $chunk = gzread($gzip, 65536);
                if ($chunk === false) throw new \RuntimeException('Falha na leitura do backup.');
                yield $chunk;
            }
        })();
        try {
            $restore = new Process(array_merge($args, ['--single-transaction', '--file=-']), null, $environment, $stream);
            $restore->setTimeout(null);
            $restore->mustRun();
        } finally {
            gzclose($gzip);
        }
        $check->mustRun();
        if ((int) trim($check->getOutput()) < 1) throw new \RuntimeException('Restauração sem tabelas.');
        $this->info('Restauração PostgreSQL confirmada no banco isolado.');
    }
}
