<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\Install\InstallerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InstallController extends Controller
{
    public function __construct(private InstallerService $installer) {}

    /** Render the setup wizard, or bounce away if the app is already installed. */
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('admin.login');
        }

        $connection = in_array(config('database.default'), ['mysql', 'pgsql'], true) ? config('database.default') : 'pgsql';

        return Inertia::render('Install/Setup', [
            'requirements' => $this->installer->requirements(),
            'defaults' => [
                'app_name' => config('app.name', 'CREASY CRM'),
                'app_url' => config('app.url') === 'https://seu-dominio.example' ? $request->getSchemeAndHttpHost() : config('app.url', 'http://localhost'),
                'app_env' => config('app.env', 'production'),
                'db_connection' => $connection,
                'db_host' => (string) config("database.connections.$connection.host", '127.0.0.1'),
                'db_port' => (string) config("database.connections.$connection.port", '3306'),
                'db_database' => (string) config("database.connections.$connection.database", ''),
                'db_username' => (string) config("database.connections.$connection.username", ''),
            ],
        ]);
    }

    /** Live connection check for the "Test connection" button (JSON). */
    public function testDatabase(Request $request): JsonResponse
    {
        if ($this->installer->isInstalled()) {
            abort(404);
        }

        $this->authorizeInstaller($request);

        $data = $request->validate([
            'db_connection' => ['required', 'in:mysql,pgsql'],
            'db_host' => ['required', 'string'],
            'db_port' => ['nullable', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        $db = $this->dbCredentials($data);
        try {
            $this->installer->saveDatabaseConfig($db);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'db_database' => 'Não foi possível salvar a configuração. Permita que o PHP escreva no arquivo .env e na pasta bootstrap/cache.',
            ]);
        }

        return response()->json($this->installer->testConnection($db));
    }

    /** Run the full install: write env, migrate, seed, create admin, lock. */
    public function run(Request $request): RedirectResponse
    {
        if ($this->installer->isInstalled()) {
            return redirect()->route('admin.login');
        }

        $this->authorizeInstaller($request);

        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url'],
            'app_env' => ['required', 'in:production,local'],
            'db_connection' => ['required', 'in:mysql,pgsql'],
            'db_host' => ['required', 'string'],
            'db_port' => ['nullable', 'integer', 'between:1,65535'],
            'db_database' => ['required', 'string'],
            'db_username' => ['required', 'string'],
            'db_password' => ['nullable', 'string'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
            'import_demo' => ['boolean'],
            'app_tagline' => ['nullable', 'string', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png', 'max:512'],
        ]);

        if (! $this->installer->requirements($data['db_connection'])['ok']) {
            throw ValidationException::withMessages(['install' => 'Corrija os requisitos do servidor antes de instalar.']);
        }
        $this->installer->beginInstallation();
        $db = $this->dbCredentials($data);

        // 1. Verify the database is reachable before persisting anything.
        $test = $this->installer->testConnection($db);
        if (! $test['ok']) {
            throw ValidationException::withMessages(['db_database' => $test['message']]);
        }

        $this->installer->assertFreshDatabase();

        // 2. Persist environment (DB creds + app info). NOT APP_INSTALLED yet.
        $env = [
            'APP_NAME' => $data['app_name'],
            'APP_URL' => $data['app_url'],
            'APP_ENV' => $data['app_env'],
            'APP_DEBUG' => 'false',
            'APP_LOCALE' => 'pt',
            'APP_TIMEZONE' => 'America/Sao_Paulo',
            'DB_CONNECTION' => $db['connection'],
            'DB_URL' => '',
            'DB_CHARSET' => $db['connection'] === 'pgsql' ? 'utf8' : 'utf8mb4',
            'DB_HOST' => $db['host'],
            'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'],
            'DB_PASSWORD' => $db['password'],
            'HEALTHZ_TOKEN' => Str::random(64),
            'SESSION_SECURE_COOKIE' => str_starts_with(strtolower($data['app_url']), 'https://') ? 'true' : 'false',
        ];
        $this->installer->writeEnv($env);
        $this->installer->ensureAppKey();

        // 3. Migrate + seed against the new connection. This is the long part.
        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $this->installer->applyDatabaseConfig($db);
            config(['app.name' => $data['app_name'], 'app.url' => $data['app_url']]);
            $this->installer->runMigrations();
            $this->installer->seedCore();
            if ($request->boolean('import_demo')) {
                $this->installer->seedDemo();
            }
            $this->installer->applyBranding($data, $request->only(['logo', 'favicon']));
            $this->installer->createSuperAdmin(
                $data['admin_name'],
                $data['admin_email'],
                $data['admin_password'],
            );

            // 4. Lock the installer and refresh caches. Written LAST so any
            //    failure above leaves the wizard reachable for a clean retry.
            $this->installer->markInstalled();
            $this->installer->clearCaches();
            try {
                app(\App\Services\CommunityRegistration::class)->queue($data['admin_name'], $data['admin_email'], $data['app_url']);
            } catch (\Throwable) {
                // Registration availability must not invalidate a completed install.
            }
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'install' => 'Não foi possível concluir a instalação. Consulte storage/logs/laravel.log antes de tentar novamente.',
            ]);
        }

        return redirect()->route('admin.login')
            ->with('status', 'Instalação concluída. Entre com a conta de administrador criada.');
    }

    private function authorizeInstaller(Request $request): void
    {
        $token = (string) config('app.install_token');
        if (strlen($token) < 32 || ! hash_equals($token, (string) $request->input('install_token', ''))) {
            throw ValidationException::withMessages(['install_token' => 'Copie a chave do arquivo storage/app/private/installation-key.txt pelo gerenciador de arquivos da hospedagem.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{host: string, port: string, database: string, username: string, password: string}
     */
    private function dbCredentials(array $data): array
    {
        return [
            'connection' => $data['db_connection'],
            'host' => $data['db_host'],
            'port' => (string) (($data['db_port'] ?? '') !== '' ? $data['db_port'] : ($data['db_connection'] === 'pgsql' ? 5432 : 3306)),
            'database' => $data['db_database'],
            'username' => $data['db_username'],
            'password' => $data['db_password'] ?? '',
        ];
    }
}
