<?php

namespace Tests\Feature\Database;

use App\Services\AnalyticsService;
use App\Services\Install\InstallerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_time_expressions_execute_on_the_active_database(): void
    {
        Schema::create('report_time_probe', function (Blueprint $table) {
            $table->timestamp('started');
            $table->timestamp('finished');
        });
        try {
            DB::table('report_time_probe')->insert(['started' => '2026-01-01 10:15:00', 'finished' => '2026-01-01 11:17:03']);
            $service = app(AnalyticsService::class);
            $expression = fn (string $name, array $args) => (new \ReflectionMethod($service, $name))->invokeArgs($service, $args);
            $row = DB::table('report_time_probe')->selectRaw(
                $expression('hourExpression', ['started']).' as hour_label, '.
                $expression('weekExpression', ['started']).' as week_label, '.
                $expression('secondsBetweenExpression', ['started', 'finished']).' as elapsed'
            )->first();
            $this->assertSame('2026-01-01 10:00', $row->hour_label);
            $this->assertSame('2026-00', $row->week_label);
            $this->assertEquals(3723, $row->elapsed);
        } finally {
            Schema::dropIfExists('report_time_probe');
        }
    }

    public function test_installer_passes_postgres_credentials_to_connection_check(): void
    {
        config(['app.install_token' => str_repeat('a', 64)]);
        $this->partialMock(InstallerService::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(false);
            $mock->shouldReceive('testConnection')->once()->with(\Mockery::on(fn ($db) =>
                $db['connection'] === 'pgsql' && $db['port'] === '5432'
            ))->andReturn(['ok' => true]);
        });
        $this->postJson(route('install.test-database'), [
            'install_token' => str_repeat('a', 64), 'db_connection' => 'pgsql',
            'db_host' => '127.0.0.1', 'db_port' => 5432, 'db_database' => 'isolated_test',
            'db_username' => 'tester', 'db_password' => '',
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_installer_rejects_unsupported_database_before_connecting(): void
    {
        config(['app.install_token' => str_repeat('a', 64)]);
        $this->partialMock(InstallerService::class, function ($mock) {
            $mock->shouldReceive('isInstalled')->andReturn(false);
            $mock->shouldNotReceive('testConnection');
        });
        $this->postJson(route('install.test-database'), [
            'install_token' => str_repeat('a', 64), 'db_connection' => 'sqlsrv',
            'db_host' => '127.0.0.1', 'db_port' => 5432, 'db_database' => 'isolated_test', 'db_username' => 'tester',
        ])->assertUnprocessable()->assertJsonValidationErrors('db_connection');
    }
}
