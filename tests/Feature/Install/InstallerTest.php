<?php
namespace Tests\Feature\Install;

use App\Models\AdminUser;
use App\Models\SystemSetting;
use App\Services\Install\EnvWriter;
use App\Services\Install\InstallerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    public function test_env_values_round_trip_without_expanding_passwords(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'env-test-');
        try {
            $values = ['DB_PASSWORD' => 'a${APP_NAME}#"\\! space', 'APP_NAME' => 'Minha marca', 'APP_INSTALLED' => true];
            (new EnvWriter($file))->set($values);
            $parsed = \Dotenv\Dotenv::parse(file_get_contents($file));
            $this->assertSame($values['DB_PASSWORD'], $parsed['DB_PASSWORD']);
            $this->assertSame('Minha marca', $parsed['APP_NAME']);
            $this->assertSame('true', $parsed['APP_INSTALLED']);
        } finally { unlink($file); }
    }

    public function test_installed_site_blocks_installer_posts(): void
    {
        config(['app.installed' => true]);
        $this->postJson(route('install.test-database'), [])->assertNotFound();
        $this->post(route('install.run'), [])->assertRedirect(route('admin.login'));
    }

    public function test_installation_requires_private_token_before_database_access(): void
    {
        $this->partialMock(InstallerService::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(false);
            $mock->shouldNotReceive('testConnection');
        });
        config(['app.install_token' => str_repeat('a', 64)]);
        $this->postJson(route('install.test-database'), ['install_token' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('install_token');
        $this->postJson(route('install.run'), [])->assertUnprocessable()->assertJsonValidationErrors('install_token');
    }

    public function test_installer_saves_branding_admin_and_locks_only_after_success(): void
    {
        $registration = $this->mock(\App\Services\CommunityRegistration::class);
        $registration->shouldReceive('queue')->once();
        $registration->shouldReceive('deliver')->once();
        Storage::fake('public');
        config(['app.install_token' => str_repeat('a', 64)]);
        $this->partialMock(InstallerService::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(false);
            $mock->shouldReceive('requirements')->andReturn(['ok'=>true]);
            $mock->shouldReceive('beginInstallation')->once();
            $mock->shouldReceive('testConnection')->andReturn(['ok'=>true]);
            $mock->shouldReceive('writeEnv')->once();
            $mock->shouldReceive('ensureAppKey')->once();
            $mock->shouldReceive('applyDatabaseConfig')->once();
            $mock->shouldReceive('runMigrations')->once();
            $mock->shouldReceive('markInstalled')->once()->ordered();
            $mock->shouldReceive('clearCaches')->once()->ordered();
        });
        $this->post(route('install.run'), [
            'install_token'=>str_repeat('a',64), 'app_name'=>'Minha Empresa', 'app_url'=>'https://chat.example.com', 'app_env'=>'production',
            'app_tagline'=>'Atendimento humano', 'support_email'=>'suporte@example.com', 'primary_color'=>'#112233',
            'logo'=>UploadedFile::fake()->image('logo.png'), 'favicon'=>UploadedFile::fake()->image('icon.png',32,32),
            'db_connection'=>'mysql', 'db_host'=>'127.0.0.1', 'db_port'=>'', 'db_database'=>'new_app', 'db_username'=>'installer', 'db_password'=>'private',
            'admin_name'=>'Administrador', 'admin_email'=>'owner@example.com', 'admin_password'=>'StrongPassword123!', 'admin_password_confirmation'=>'StrongPassword123!',
            'import_demo'=>false,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.login'));
        $this->assertSame('Minha Empresa', SystemSetting::get('app_name'));
        $this->assertSame('#112233', SystemSetting::get('primary_color'));
        Storage::disk('public')->assertExists(SystemSetting::get('app_logo_path'));
        Storage::disk('public')->assertExists(SystemSetting::get('app_favicon_path'));
        $this->assertTrue(AdminUser::where('email','owner@example.com')->firstOrFail()->isSuperAdmin());
        $this->assertDatabaseCount('clients', 0);
    }

    public function test_database_port_is_optional_and_defaults_to_selected_driver(): void
    {
        config(['app.install_token' => str_repeat('a', 64)]);
        $this->partialMock(InstallerService::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(false);
            $mock->shouldReceive('saveDatabaseConfig')->times(3);
            foreach (['3306', '5432', '3307'] as $port) {
                $mock->shouldReceive('testConnection')->once()
                    ->with(\Mockery::on(fn ($db) => $db['port'] === $port))
                    ->andReturn(['ok' => true]);
            }
        });
        $data = ['install_token' => str_repeat('a', 64), 'db_connection' => 'mysql',
            'db_host' => 'localhost', 'db_database' => 'new_app', 'db_username' => 'installer'];
        $this->postJson(route('install.test-database'), $data)->assertOk()->assertJsonPath('ok', true);
        $this->postJson(route('install.test-database'), array_merge($data, ['db_connection' => 'pgsql', 'db_port' => '']))->assertOk();
        $this->postJson(route('install.test-database'), array_merge($data, ['db_port' => '3307']))->assertOk();
        $this->postJson(route('install.test-database'), array_merge($data, ['db_port' => '70000']))->assertUnprocessable()->assertJsonValidationErrors('db_port');
    }

    public function test_database_form_is_saved_before_testing_connection(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'installer-env-');
        file_put_contents($file, "APP_KEY=keep-this-key\nAPP_INSTALLED=false\nDB_DATABASE=old_database\n");
        $password = 'test$with#special!characters';
        config(['app.install_token' => str_repeat('a', 64)]);
        \Illuminate\Support\Facades\Artisan::shouldReceive('call')->never();
        $service = \Mockery::mock(InstallerService::class, [new EnvWriter($file)])->makePartial();
        $service->shouldReceive('applyDatabaseConfig')->once();
        $service->shouldReceive('isInstalled')->andReturn(false);
        $service->shouldReceive('testConnection')->once()->andReturnUsing(function ($db) use ($file, $password) {
            $env = \Dotenv\Dotenv::parse(file_get_contents($file));
            $this->assertSame('new_database', $env['DB_DATABASE']);
            $this->assertSame('new_user', $env['DB_USERNAME']);
            $this->assertSame($password, $env['DB_PASSWORD']);
            $this->assertSame('3306', $env['DB_PORT']);
            $this->assertSame('keep-this-key', $env['APP_KEY']);
            $this->assertSame('false', $env['APP_INSTALLED']);
            $this->assertSame($env['DB_PASSWORD'], $db['password']);
            return ['ok' => true];
        });
        $this->app->instance(InstallerService::class, $service);
        try {
            $this->postJson(route('install.test-database'), [
                'install_token' => str_repeat('a', 64), 'db_connection' => 'mysql',
                'db_host' => 'localhost', 'db_database' => 'new_database',
                'db_username' => 'new_user', 'db_password' => $password,
            ])->assertOk()->assertJsonPath('ok', true);
        } finally {
            unlink($file);
        }
    }

    public function test_first_database_check_uses_form_values_with_stale_configuration(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'installer-first-check-');
        file_put_contents($file, "DB_DATABASE=old_database\nAPP_INSTALLED=false\n");
        config(['database.connections.mysql' => [
            'driver' => 'mysql', 'host' => 'old-host', 'database' => 'old_database',
            'username' => 'old_user', 'password' => 'old_password', 'port' => '9999',
            'url' => 'mysql://old:old@old-host/old_database',
            'read' => ['host' => 'old-reader'], 'write' => ['host' => 'old-writer'],
        ], 'app.install_token' => str_repeat('a', 64), 'app.installed' => false]);
        $pdo = \Mockery::mock(\PDO::class);
        \Illuminate\Support\Facades\DB::extend('mysql', function ($config, $name) use ($pdo) {
            $this->assertSame('new-host', $config['host']);
            $this->assertSame('new_database', $config['database']);
            $this->assertSame('new_user', $config['username']);
            $this->assertSame('new_password', $config['password']);
            $this->assertSame('3306', $config['port']);
            $this->assertNull($config['url'] ?? null);
            $this->assertArrayNotHasKey('read', $config);
            $this->assertArrayNotHasKey('write', $config);
            return new \Illuminate\Database\MySqlConnection($pdo, $config['database'], '', $config);
        });
        \Illuminate\Support\Facades\Artisan::shouldReceive('call')->never();
        $service = \Mockery::mock(InstallerService::class, [new EnvWriter($file)])->makePartial();
        $service->shouldReceive('isInstalled')->andReturn(false);
        $this->app->instance(InstallerService::class, $service);
        try {
            $this->postJson(route('install.test-database'), [
                'install_token' => str_repeat('a', 64), 'db_connection' => 'mysql',
                'db_host' => 'new-host', 'db_database' => 'new_database',
                'db_username' => 'new_user', 'db_password' => 'new_password',
            ])->assertOk()->assertJsonPath('ok', true);
        } finally {
            \Illuminate\Support\Facades\DB::purge('mysql');
            config(['database.default' => 'sqlite']);
            unlink($file);
        }
    }

    public function test_installer_page_does_not_query_database_before_setup(): void
    {
        config(['app.url' => 'https://seu-dominio.example']);
        $this->partialMock(InstallerService::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(false);
        });
        $queries = [];
        \Illuminate\Support\Facades\DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        $this->get('/install')->assertOk();
        $this->assertSame([], $queries, 'The installer must not query application tables.');
    }

    public function test_cli_refuses_to_reinstall_existing_application(): void
    {
        config(['app.installed'=>true]);
        $this->artisan('saas:install')->assertFailed();
    }
}
