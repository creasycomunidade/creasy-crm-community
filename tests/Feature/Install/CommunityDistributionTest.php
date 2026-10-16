<?php

namespace Tests\Feature\Install;

use App\Services\CommunityRegistration;
use App\Support\FirstRunBootstrap;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CommunityDistributionTest extends TestCase
{
    private string $temporary;
    private string $originalStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalStorage = storage_path();
        $this->temporary = sys_get_temp_dir().'/creasy-distribution-'.bin2hex(random_bytes(8));
        mkdir($this->temporary.'/storage/app/private', 0775, true);
        $this->app->useStoragePath($this->temporary.'/storage');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStorage);
        app('files')->deleteDirectory($this->temporary);
        parent::tearDown();
    }

    public function test_download_generates_private_unique_keys_and_preserves_existing_environment(): void
    {
        copy(base_path('.env.example'), $this->temporary.'/.env.example');
        FirstRunBootstrap::prepare($this->temporary);
        $first = file_get_contents($this->temporary.'/.env');
        $env = \Dotenv\Dotenv::parse($first);
        $this->assertSame(64, strlen($env['INSTALL_TOKEN']));
        $this->assertSame(32, strlen(base64_decode(substr($env['APP_KEY'], 7))));
        $this->assertSame('false', $env['APP_INSTALLED']);
        $this->assertStringContainsString($env['INSTALL_TOKEN'], file_get_contents($this->temporary.'/storage/app/private/installation-key.txt'));
        FirstRunBootstrap::prepare($this->temporary);
        $this->assertSame($first, file_get_contents($this->temporary.'/.env'));
        mkdir($this->temporary.'/second');
        copy(base_path('.env.example'), $this->temporary.'/second/.env.example');
        FirstRunBootstrap::prepare($this->temporary.'/second');
        $second = \Dotenv\Dotenv::parse(file_get_contents($this->temporary.'/second/.env'));
        $this->assertNotSame($env['APP_KEY'], $second['APP_KEY']);
        $this->assertNotSame($env['INSTALL_TOKEN'], $second['INSTALL_TOKEN']);
    }

    public function test_registration_only_sends_allowlisted_fields_and_removes_personal_data_after_success(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $service = app(CommunityRegistration::class);
        $service->queue('Administrador Teste', 'admin@example.com', 'https://crm.example.com/path');
        $path = storage_path('app/private/community-registration.json');
        $this->assertStringNotContainsString('admin@example.com', file_get_contents($path));
        $service->deliver();
        Http::assertSent(function ($request) {
            $keys = array_keys($request->data()); sort($keys);
            $this->assertSame(['admin_email', 'admin_name', 'domain', 'installation_id', 'installation_secret', 'version'], $keys);
            return $request['admin_email'] === 'admin@example.com' && $request['domain'] === 'crm.example.com';
        });
        $this->assertStringNotContainsString('pending', file_get_contents($path));
        $service->deliver();
        Http::assertSentCount(1);
    }

    public function test_collector_failure_is_non_blocking_and_does_not_repeat_on_every_visit(): void
    {
        Http::fake(['*' => Http::response(['ok' => false], 503)]);
        $service = app(CommunityRegistration::class);
        $service->queue('Teste', 'admin@example.com', 'https://crm.example.com');
        $service->deliver(); $service->deliver();
        Http::assertSentCount(1);
        $state = json_decode(file_get_contents(storage_path('app/private/community-registration.json')), true);
        $this->assertArrayHasKey('pending', $state);
        $this->assertGreaterThan(time(), $state['next_attempt']);
    }

    public function test_heartbeat_is_authenticated_throttled_and_does_not_repeat_personal_data(): void
    {
        Http::fake(['*' => Http::response(['ok' => true])]);
        $service = app(CommunityRegistration::class);
        $service->queue('Teste', 'admin@example.com', 'https://crm.example.com');
        $service->deliver();
        $path = storage_path('app/private/community-registration.json');
        $state = json_decode(file_get_contents($path), true);
        $this->assertStringNotContainsString('admin@example.com', file_get_contents($path));
        $this->assertArrayHasKey('heartbeat_credentials', $state);
        $service->deliver();
        Http::assertSentCount(1);
        $state['next_attempt'] = 0;
        file_put_contents($path, json_encode($state));
        $service->deliver();
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            if ($request->url() !== config('community.heartbeat_url')) return false;
            $keys = array_keys($request->data()); sort($keys);
            return $keys === ['installation_id', 'installation_secret'];
        });
        $service->deliver();
        Http::assertSentCount(2);
    }

    public function test_legacy_receipt_does_not_send_unauthed_heartbeats(): void
    {
        Http::fake();
        file_put_contents(storage_path('app/private/community-registration.json'), json_encode(['installation_id' => 'legacy', 'registered_at' => gmdate(DATE_ATOM)]));
        app(CommunityRegistration::class)->deliver();
        Http::assertNothingSent();
    }
}
