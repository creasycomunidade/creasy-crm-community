<?php

namespace App\Services\Install;

use App\Models\AdminUser;
use App\Models\Role;
use App\Modules\Integrations\Database\Seeders\IntegrationConfigSeeder;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Single source of truth for first-run installation, shared by the CLI
 * (SaasInstallCommand) and the web installer (InstallController). Keeps the
 * seeder list, super-admin creation, and cache clearing in one place.
 */
class InstallerService
{
    public const MIN_PHP_VERSION = '8.2.0';

    public const REQUIRED_EXTENSIONS = [
        'pdo', 'mbstring', 'openssl', 'tokenizer',
        'ctype', 'json', 'bcmath', 'fileinfo', 'curl', 'dom', 'xml', 'zip', 'gd',
    ];

    /**
     * Core data required for the app to run (no demo content). Order matters:
     * permissions must exist before RoleSeeder syncs them. Mirrors the list in
     * SaasInstallCommand; every seeder is idempotent (firstOrCreate/updateOrCreate).
     */
    public const CORE_SEEDERS = [
        PermissionSeeder::class,
        RoleSeeder::class,
        CurrencySeeder::class,
        EmailTemplateSeeder::class,
        IntegrationConfigSeeder::class,
        CmsPageSeeder::class,
    ];

    public function __construct(private EnvWriter $env) {}

    public function isInstalled(): bool
    {
        return (bool) config('app.installed') || is_file(storage_path('installed.lock'));
    }

    /**
     * Pre-flight check results for the requirements step.
     *
     * @return array{php: array{name: string, current: string, passed: bool}, extensions: list<array{name: string, passed: bool}>, writable: list<array{name: string, passed: bool}>, ok: bool}
     */
    public function requirements(?string $connection = null): array
    {
        $php = [
            'name' => 'PHP >= '.self::MIN_PHP_VERSION,
            'current' => PHP_VERSION,
            'passed' => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>='),
        ];

        $extensions = array_map(fn (string $ext) => [
            'name' => $ext,
            'passed' => extension_loaded($ext),
        ], self::REQUIRED_EXTENSIONS);

        $driver = $connection ?? config('database.default', 'pgsql');
        $extensions[] = [
            'name' => $connection === null ? 'pdo_pgsql ou pdo_mysql' : 'pdo_'.$driver,
            'passed' => $connection === null
                ? (extension_loaded('pdo_pgsql') || extension_loaded('pdo_mysql'))
                : extension_loaded('pdo_'.$driver),
        ];

        $writable = [];
        foreach ([
            '.env' => base_path('.env'),
            'storage/' => storage_path(),
            'bootstrap/cache/' => base_path('bootstrap/cache'),
        ] as $label => $path) {
            $writable[] = ['name' => $label, 'passed' => $this->isWritable($path)];
        }

        $ok = $php['passed']
            && ! in_array(false, array_column($extensions, 'passed'), true)
            && ! in_array(false, array_column($writable, 'passed'), true);

        return ['php' => $php, 'extensions' => $extensions, 'writable' => $writable, 'ok' => $ok];
    }

    /**
     * Test a database connection at runtime without persisting anything.
     *
     * @param  array{connection?: string, host: string, port: string|int, database: string, username: string, password?: string}  $db
     * @return array{ok: bool, message: string}
     */
    public function saveDatabaseConfig(array $db): void
    {
        $connection = $db['connection'] ?? 'mysql';
        $this->env->set([
            'DB_CONNECTION' => $connection,
            'DB_URL' => '',
            'DB_SOCKET' => '',
            'DB_CHARSET' => $connection === 'pgsql' ? 'utf8' : 'utf8mb4',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) ($db['port'] ?: ($connection === 'pgsql' ? 5432 : 3306)),
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'] ?? '',
        ]);
        // Refresh this request too: saving .env does not reload Laravel's config.
        $this->applyDatabaseConfig($db);

        // Clear the file directly. Booting Artisan here can resolve commands and
        // database-dependent services before a fresh database has been installed.
        $cachePath = app()->getCachedConfigPath();
        if (is_file($cachePath) && ! app('files')->delete($cachePath)) {
            throw new \RuntimeException('Não foi possível limpar o cache de configuração.');
        }
    }

    public function testConnection(array $db): array
    {
        $connection = $db['connection'] ?? 'pgsql';

        try {
            $this->applyDatabaseConfig($db);
            DB::connection($connection)->getPdo();

            return ['ok' => true, 'message' => 'Connection successful — the database is reachable.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->friendlyDbError($e)];
        }
    }

    /**
     * Point the live request's database connection at the supplied credentials
     * and reconnect. MUST be called before migrating/seeding — writing .env does
     * not change the connection already resolved for this request.
     *
     * @param  array{connection?: string, host: string, port: string|int, database: string, username: string, password?: string}  $db
     */
    public function applyDatabaseConfig(array $db): void
    {
        $connection = $db['connection'] ?? 'pgsql';
        if (! in_array($connection, ['mysql', 'pgsql'], true)) {
            throw new \InvalidArgumentException('Banco não suportado.');
        }
        $base = config("database.connections.$connection", []);
        // Old read/write overrides must not supersede the submitted credentials.
        unset($base['read'], $base['write']);

        config([
            "database.connections.$connection" => array_merge($base, [
                'url' => null,
                'unix_socket' => '',
                'charset' => $connection === 'pgsql' ? 'utf8' : 'utf8mb4',
                'host' => $db['host'],
                'port' => (string) $db['port'],
                'database' => $db['database'],
                'username' => $db['username'],
                'password' => $db['password'] ?? '',
            ]),
            'database.default' => $connection,
        ]);

        DB::purge($connection);
        DB::reconnect($connection);
    }

    public function ensureAppKey(): void
    {
        if (empty(config('app.key'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }
    }

    public function runMigrations(): void
    {
        $this->runCommand('migrate', ['--force' => true]);
    }

    /** Seed the core data required for the app to run (no demo content). */
    public function seedCore(): void
    {
        $this->runCommand('db:seed', ['--class' => \Database\Seeders\LocaleSeeder::class, '--force' => true]);
        $this->runCommand('db:seed', ['--class' => \Database\Seeders\TranslationSeeder::class, '--force' => true]);

        foreach (self::CORE_SEEDERS as $seeder) {
            $this->runCommand('db:seed', ['--class' => $seeder, '--force' => true]);
        }
    }

    /**
     * Seed sample/demo content: one fully-populated demo client (SpaGreen
     * Wellness — client@spagreen.net) covering every module, plus a couple of
     * lighter secondary clients. DatabaseSeeder runs DemoSeeder as its final
     * step, so a single call covers everything.
     */
    public function seedDemo(): void
    {
        $this->runCommand('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    }

    public function createSuperAdmin(string $name, string $email, string $password): AdminUser
    {
        $admin = AdminUser::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => AdminUser::STATUS_ACTIVE,
            ]
        );

        $superAdminRole = Role::where('key', Role::KEY_SUPER_ADMIN)->first();
        if ($superAdminRole && ! $admin->roles()->where('roles.id', $superAdminRole->id)->exists()) {
            $admin->roles()->attach($superAdminRole->id);
        }

        return $admin;
    }

    public function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        try {
            Artisan::call('cache:clear');
        } catch (\Throwable) {
            // The cache store may be unavailable on a fresh box; non-fatal.
        }
    }

    /**
     * Persist environment values written by the installer (DB creds, app info).
     *
     * @param  array<string, string|int|bool|null>  $pairs
     */
    public function writeEnv(array $pairs): void
    {
        $this->env->set($pairs);
    }

    /** Final step: flip APP_INSTALLED so the wizard locks itself. */
    public function markInstalled(): void
    {
        if (file_put_contents(storage_path('installed.lock'), date(DATE_ATOM), LOCK_EX) === false) {
            throw new \RuntimeException('Não foi possível bloquear o instalador.');
        }
        $this->env->set(['APP_INSTALLED' => 'true', 'INSTALL_TOKEN' => '']);
        $keyFile = storage_path('app/private/installation-key.txt');
        if (is_file($keyFile)) @unlink($keyFile);
        config(['app.installed' => true]);
    }

    private $installationLock;

    public function beginInstallation(): void
    {
        $this->installationLock = fopen(storage_path('framework/install.lock'), 'c');
        if (! $this->installationLock || ! flock($this->installationLock, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Outra instalação está em andamento.');
        }
        if ($this->isInstalled()) {
            throw new \RuntimeException('Esta instalação já foi concluída.');
        }
    }

    public function assertFreshDatabase(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('admin_users') && AdminUser::exists()) {
            throw new \RuntimeException('Este banco já possui administradores. Use um banco novo; não reinstale sobre dados existentes.');
        }
    }

    public function applyBranding(array $data, array $files = []): void
    {
        foreach (['app_name', 'app_tagline', 'support_email', 'primary_color'] as $key) {
            if (isset($data[$key])) {
                \App\Models\SystemSetting::set($key, $data[$key], false, 'general');
            }
        }
        foreach (['logo', 'favicon'] as $kind) {
            if (! empty($files[$kind])) {
                $path = $files[$kind]->store('branding', 'public');
                if (! $path) {
                    throw new \RuntimeException('Falha ao salvar a identidade visual.');
                }
                \App\Models\SystemSetting::set('app_'.$kind.'_path', $path, false, 'general');
                \App\Models\SystemSetting::set('app_'.$kind.'_disk', 'public', false, 'general');
            }
        }
    }

    private function runCommand(string $command, array $arguments = []): void
    {
        if (Artisan::call($command, $arguments) !== 0) {
            throw new \RuntimeException('Falha na etapa '.$command.'. Consulte os logs do servidor.');
        }
    }

    private function isWritable(string $path): bool
    {
        if (is_file($path) || is_dir($path)) {
            return is_writable($path);
        }

        // .env may not exist yet — the parent dir must be writable to create it.
        return is_writable(dirname($path));
    }

    private function friendlyDbError(\Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            str_contains($message, 'Unknown database') => 'That database does not exist on the server. Create it first, then retry.',
            str_contains($message, 'Access denied') => 'O MySQL recusou o acesso. Confira o usuário, a senha e a permissão desse usuário sobre o banco na hospedagem.',
            str_contains($message, 'Operation not permitted') => 'A hospedagem bloqueou a conexão com esse host. Use o endereço MySQL informado no painel da hospedagem; se o banco for local, confira se o host indicado é localhost em vez de 127.0.0.1.',
            str_contains($message, 'Connection refused'),
            str_contains($message, 'getaddrinfo'),
            str_contains($message, 'php_network_getaddresses'),
            str_contains($message, 'timed out'),
            str_contains($message, "Can't connect") => 'Could not reach the database server — check the host and port.',
            default => 'Não foi possível conectar ao banco. Confira os dados e os logs do servidor.',
        };
    }
}
