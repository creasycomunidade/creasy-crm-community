<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureNotDemoMode;
use App\Modules\Shared\Models\Contact;
use App\Support\ApiAbilities;
use App\Support\Demo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    // ── Demo helper maskers (pure) ────────────────────────────────────────────

    public function test_mask_phone_keeps_country_prefix_and_last_two_digits(): void
    {
        $masked = Demo::maskPhone('+8801712345678');

        $this->assertStringStartsWith('+88', $masked);
        $this->assertStringEndsWith('78', $masked);
        $this->assertStringNotContainsString('1712345', $masked);
    }

    public function test_mask_email_hides_local_and_domain_but_keeps_tld(): void
    {
        $masked = Demo::maskEmail('johnsmith@example.com');

        $this->assertStringEndsWith('.com', $masked);
        $this->assertStringNotContainsString('johnsmith', $masked);
        $this->assertStringNotContainsString('example', $masked);
    }

    public function test_mask_name_keeps_only_first_letter_of_each_part(): void
    {
        $masked = Demo::maskName('John Smith');

        $this->assertStringStartsWith('J', $masked);
        $this->assertStringNotContainsString('ohn', $masked);
        $this->assertStringNotContainsString('mith', $masked);
    }

    public function test_mask_text_scrubs_embedded_email_and_phone(): void
    {
        $masked = Demo::maskText('Reach me at john@example.com or +8801712345678 anytime');

        $this->assertStringContainsString('Reach me at', $masked);
        $this->assertStringNotContainsString('john@example.com', $masked);
        $this->assertStringNotContainsString('1712345678', $masked);
    }

    public function test_conditional_helpers_are_noop_when_demo_off(): void
    {
        config(['app.demo_mode' => false]);

        $this->assertSame('+8801712345678', Demo::phone('+8801712345678'));
        $this->assertSame('john@example.com', Demo::email('john@example.com'));
        $this->assertFalse(Demo::active());
    }

    public function test_conditional_helpers_mask_when_demo_on(): void
    {
        config(['app.demo_mode' => true]);

        $this->assertNotSame('+8801712345678', Demo::phone('+8801712345678'));
        $this->assertTrue(Demo::active());
    }

    // ── Model serialization masking ───────────────────────────────────────────

    public function test_contact_to_array_masks_pii_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $contact = Contact::factory()->create([
            'workspace_id' => 1,
            'first_name' => 'Johnathan',
            'last_name' => 'Smith',
            'phone_e164' => '+8801712345678',
            'email' => 'johnathan@example.com',
            'custom_fields' => ['vip_note' => 'top customer'],
        ]);

        $array = $contact->toArray();

        $this->assertStringNotContainsString('Johnathan', $array['first_name']);
        $this->assertStringNotContainsString('johnathan@example.com', $array['email']);
        $this->assertStringNotContainsString('1712345', $array['phone_e164']);
        $this->assertStringNotContainsString('Johnathan', $array['full_name']);
        $this->assertNull($array['avatar_url']);
        $this->assertSame(['vip_note' => '••••••'], $array['custom_fields']);
    }

    public function test_contact_to_array_is_untouched_when_demo_off(): void
    {
        config(['app.demo_mode' => false]);

        $contact = Contact::factory()->create([
            'workspace_id' => 1,
            'first_name' => 'Johnathan',
            'phone_e164' => '+8801712345678',
        ]);

        $array = $contact->toArray();

        $this->assertSame('Johnathan', $array['first_name']);
        $this->assertSame('+8801712345678', $array['phone_e164']);
    }

    public function test_direct_attribute_access_keeps_real_value_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $contact = Contact::factory()->create([
            'workspace_id' => 1,
            'phone_e164' => '+8801712345678',
        ]);

        // Internal logic (sending, dedup) must still see the real value.
        $this->assertSame('+8801712345678', $contact->phone_e164);
    }

    // ── API surface: masked reads + blocked writes ────────────────────────────

    public function test_api_contacts_index_masks_pii_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        ['user' => $user, 'workspace' => $workspace] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        Contact::factory()->create([
            'workspace_id' => $workspace->id,
            'first_name' => 'Johnathan',
            'phone_e164' => '+8801712345678',
            'email' => 'johnathan@example.com',
        ]);

        $res = $this->withToken($token)->getJson('/api/v1/contacts')->assertOk();

        $body = json_encode($res->json());
        $this->assertStringNotContainsString('Johnathan', $body);
        $this->assertStringNotContainsString('johnathan@example.com', $body);
        $this->assertStringNotContainsString('1712345678', $body);
    }

    public function test_api_contact_write_is_blocked_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_WRITE])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/contacts', [
                'phone_e164' => '+8801700000001',
                'first_name' => 'Rahim',
                'opt_in_whatsapp' => true,
            ])
            ->assertStatus(403)
            ->assertJson(['code' => 'demo_mode']);

        $this->assertDatabaseMissing('contacts', ['phone_e164' => '+8801700000001']);
    }

    public function test_api_reads_still_work_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        ['user' => $user] = $this->createWorkspaceContext();
        $token = $user->createToken('t', [ApiAbilities::CONTACTS_READ])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/contacts')->assertOk();
    }

    // ── Inertia (SPA) write path: 403 the front-end can detect ────────────────

    public function test_inertia_write_returns_detectable_403_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        $request = Request::create('/contacts', 'POST');
        $request->headers->set('X-Inertia', 'true');

        $response = (new EnsureNotDemoMode)->handle($request, fn () => response('should not run'));

        // The SPA keeps every button/field live; the attempt reaches the server,
        // is blocked here, and comes back as a 403 carrying a stable `code` that
        // app.jsx turns into a toast instead of Inertia's default error modal.
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('demo_mode', $response->getData(true)['code']);
    }

    public function test_writes_pass_through_when_demo_mode_off(): void
    {
        config(['app.demo_mode' => false]);

        $request = Request::create('/contacts', 'POST');
        $response = (new EnsureNotDemoMode)->handle($request, fn () => response('passed', 200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passed', $response->getContent());
    }

    public function test_impersonation_start_is_allowed_in_demo_mode(): void
    {
        config(['app.demo_mode' => true]);

        // "Log in as client" only switches the session, so it stays usable in
        // demo mode — the visitor can explore the (still write-blocked) client panel.
        $response = (new EnsureNotDemoMode)->handle(
            $this->postRequestNamed('admin.clients.impersonate'),
            fn () => response('passed', 200),
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('passed', $response->getContent());
    }

    /**
     * Build a POST request whose resolved route carries the given name, so the
     * middleware's allowlist check (which reads $request->route()->getName()) fires.
     */
    private function postRequestNamed(string $name): Request
    {
        $request = Request::create('/x', 'POST');
        $route = (new \Illuminate\Routing\Route(['POST'], '/x', []))->name($name);
        $request->setRouteResolver(fn () => $route);

        return $request;
    }
}
