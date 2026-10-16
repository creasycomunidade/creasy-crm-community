<?php

namespace Tests\Feature;

use App\Contracts\BillingGatewayInterface;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Shared\Models\Contact;
use App\Services\Billing\BillingGatewayRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ProductionReadinessJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_registration_checkout_fulfilment_and_company_isolation(): void
    {
        $plan = Plan::factory()->create([
            'name' => 'Plano Produção',
            'monthly_price_cents' => 19_900,
            'price_cents' => 19_900,
            'currency_code' => 'BRL',
            'enabled' => true,
        ]);

        $registration = $this->post('/register', [
            'name' => 'Empresa Alfa',
            'email' => 'admin@alfa.example',
            'password' => 'Senha-segura-123',
            'password_confirmation' => 'Senha-segura-123',
            'agree_terms' => true,
            'timezone' => 'America/Sao_Paulo',
            'plan_id' => $plan->id,
            'cycle' => 'month',
        ]);

        $registration->assertRedirect(route('client.pricing', absolute: false));
        $companyA = User::where('email', 'admin@alfa.example')->firstOrFail();
        $companyA->forceFill(['email_verified_at' => now()])->saveQuietly();
        $this->assertNotNull($companyA->client_id);
        $this->assertNotNull($companyA->workspace_id);
        $this->assertSame($companyA->client_id, $companyA->workspace->client_id);

        $gateway = new ProductionJourneyGateway;
        $registry = Mockery::mock(BillingGatewayRegistry::class);
        $registry->shouldReceive('get')->with('stripe')->andReturn($gateway);
        $this->app->instance(BillingGatewayRegistry::class, $registry);

        $checkout = $this->actingAs($companyA)->withHeader('X-Inertia', 'true')->post(route('client.checkout.store'), [
            'plan_id' => $plan->id,
            'billing_cycle' => 'month',
            'gateway' => 'stripe',
        ]);
        $checkout->assertStatus(409);
        $checkout->assertHeader('X-Inertia-Location', 'https://checkout.example/session/test');

        $this->actingAs($companyA)
            ->get(route('client.billing.index', ['checkout' => 'success', 'session_id' => 'session_test']))
            ->assertStatus(409);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $companyA->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'gateway' => 'stripe',
        ]);
        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $companyA->id,
            'amount_cents' => 19_900,
            'status' => 'paid',
        ]);

        Contact::factory()->create([
            'workspace_id' => $companyA->workspace_id,
            'first_name' => 'Contato Alfa',
            'phone_e164' => '+5547999999001',
        ]);

        auth()->logout();
        $this->post('/register', [
            'name' => 'Empresa Beta',
            'email' => 'admin@beta.example',
            'password' => 'Senha-segura-456',
            'password_confirmation' => 'Senha-segura-456',
            'agree_terms' => true,
            'timezone' => 'America/Sao_Paulo',
        ])->assertRedirect(route('client.dashboard', absolute: false));

        $companyB = User::where('email', 'admin@beta.example')->firstOrFail();
        $companyB->forceFill(['email_verified_at' => now()])->saveQuietly();
        $contactB = Contact::factory()->create([
            'workspace_id' => $companyB->workspace_id,
            'first_name' => 'Contato Beta',
            'phone_e164' => '+5547999999002',
        ]);

        $this->assertNotSame($companyA->client_id, $companyB->client_id);
        $this->assertNotSame($companyA->workspace_id, $companyB->workspace_id);
        $inertiaVersion = file_exists(public_path('build/manifest.json'))
            ? hash_file('xxh128', public_path('build/manifest.json'))
            : '';
        $this->actingAs($companyA)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $inertiaVersion])
            ->get(route('client.contacts.show', $contactB))
            ->assertForbidden();
        $this->actingAs($companyA)
            ->delete(route('client.contacts.destroy', $contactB))
            ->assertForbidden();
        $this->assertDatabaseHas('contacts', ['id' => $contactB->id, 'workspace_id' => $companyB->workspace_id]);
    }
}

class ProductionJourneyGateway implements BillingGatewayInterface
{
    private ?User $user = null;
    private ?Plan $plan = null;
    private string $cycle = 'month';

    public function name(): string { return 'Gateway de teste'; }
    public function isConfigured(): bool { return true; }

    public function createCheckout(User $user, Plan $plan, string $billingCycle): array
    {
        $this->user = $user;
        $this->plan = $plan;
        $this->cycle = $billingCycle;

        return ['url' => 'https://checkout.example/session/test'];
    }

    public function fulfillCheckoutSession(string $sessionId): array
    {
        $subscription = Subscription::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => $this->cycle,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'gateway' => 'stripe',
            'gateway_subscription_id' => $sessionId,
        ]);
        PaymentTransaction::create([
            'subscription_id' => $subscription->id,
            'user_id' => $this->user->id,
            'gateway' => 'stripe',
            'gateway_transaction_id' => 'payment_'.$sessionId,
            'amount_cents' => $this->plan->priceCentsForCycle($this->cycle),
            'currency_code' => $this->plan->currency_code,
            'status' => 'paid',
        ]);

        return ['ok' => true, 'error' => null, 'subscription' => $subscription];
    }

    public function handleWebhook(Request $request): Response { return new Response('ok'); }
    public function cancel(Subscription $subscription): bool { return true; }
    public function sync(Subscription $subscription): bool { return true; }
    public function changePlan(Subscription $subscription, Plan $newPlan, string $billingCycle): array { return ['ok' => true, 'error' => null]; }
    public function refund(PaymentTransaction $transaction, ?int $amountCents = null): array { return ['ok' => true, 'error' => null]; }
}
