<?php

namespace Tests\Feature\Billing;

use App\Events\SubscriptionRenewed;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\BillingPaymentFailedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the auto-renewal path against the Stripe 2025-03-31.basil API shape, where
 * `invoice.subscription` moved to `invoice.parent.subscription_details.subscription`
 * and `subscription.current_period_end` moved onto the line items. stripe-php v19 targets
 * that version, so these payloads mirror what production webhooks actually deliver.
 */
class StripeWebhookRenewalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the Stripe gateway with an empty webhook secret so the handler takes the
        // unsigned path (non-production) and parses the raw payload instead of verifying a signature.
        config([
            'billing.gateways.stripe' => [
                'enabled' => true,
                'secret_key' => 'sk_test_dummy',
                'webhook_secret' => '',
                'success_url' => 'http://localhost/app/billing?checkout=success',
                'cancel_url' => 'http://localhost/app/pricing?checkout=canceled',
            ],
        ]);

        Storage::fake('local');
    }

    private function makeSubscription(array $overrides = []): Subscription
    {
        $user = User::factory()->create(['role' => 'client']);
        $plan = Plan::factory()->create();

        return Subscription::create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'month',
            'starts_at' => now()->subMonth(),
            'renews_at' => now()->subDay(),
            'gateway' => 'stripe',
            'gateway_subscription_id' => 'sub_test_123',
        ], $overrides));
    }

    private function invoicePaidPayload(string $eventId, array $invoiceOverrides = [], ?array $parent = null, ?int $periodEnd = null): array
    {
        $periodEnd ??= Carbon::parse('2026-07-01 00:00:00')->getTimestamp();

        $invoice = array_merge([
            'object' => 'invoice',
            'id' => 'in_test_1',
            'amount_paid' => 2000,
            'amount_due' => 2000,
            'currency' => 'usd',
            'billing_reason' => 'subscription_cycle',
            'lines' => [
                'object' => 'list',
                'data' => [
                    ['period' => ['start' => $periodEnd - 2592000, 'end' => $periodEnd]],
                ],
            ],
        ], $invoiceOverrides);

        // basil shape by default; callers can pass a custom/legacy parent linkage.
        $invoice['parent'] = $parent ?? [
            'type' => 'subscription_details',
            'subscription_details' => ['subscription' => 'sub_test_123'],
        ];

        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'invoice.paid',
            'data' => ['object' => $invoice],
        ];
    }

    public function test_renewal_invoice_records_transaction_and_advances_renewal_date(): void
    {
        Event::fake([SubscriptionRenewed::class]);
        $subscription = $this->makeSubscription();
        $periodEnd = Carbon::parse('2026-07-01 00:00:00')->getTimestamp();

        $response = $this->postJson(route('webhooks.stripe'), $this->invoicePaidPayload('evt_renew_1', periodEnd: $periodEnd));

        $response->assertOk();

        $this->assertDatabaseHas('payment_transactions', [
            'gateway' => 'stripe',
            'gateway_transaction_id' => 'in_test_1',
            'subscription_id' => $subscription->id,
            'amount_cents' => 2000,
            'status' => 'paid',
        ]);

        $subscription->refresh();
        $this->assertEquals($periodEnd, $subscription->renews_at->getTimestamp(), 'renews_at should advance to the new period end');

        Event::assertDispatched(SubscriptionRenewed::class, function (SubscriptionRenewed $e) use ($subscription) {
            return $e->subscription->id === $subscription->id && $e->amountCents === 2000;
        });
    }

    public function test_first_invoice_records_transaction_but_does_not_fire_renewal(): void
    {
        Event::fake([SubscriptionRenewed::class]);
        $subscription = $this->makeSubscription();

        // billing_reason = subscription_create => the first charge, not a renewal.
        $payload = $this->invoicePaidPayload('evt_create_1', ['billing_reason' => 'subscription_create']);

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();

        $this->assertDatabaseHas('payment_transactions', [
            'gateway_transaction_id' => 'in_test_1',
            'subscription_id' => $subscription->id,
        ]);
        Event::assertNotDispatched(SubscriptionRenewed::class);
    }

    public function test_legacy_invoice_shape_still_resolves_subscription(): void
    {
        Event::fake([SubscriptionRenewed::class]);
        $subscription = $this->makeSubscription();

        // Older accounts still send the top-level `subscription` field and no `parent`.
        $payload = $this->invoicePaidPayload('evt_legacy_1', ['subscription' => 'sub_test_123'], parent: ['type' => 'manual']);

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();

        $this->assertDatabaseHas('payment_transactions', [
            'gateway_transaction_id' => 'in_test_1',
            'subscription_id' => $subscription->id,
        ]);
        Event::assertDispatched(SubscriptionRenewed::class);
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        Event::fake([SubscriptionRenewed::class]);
        $this->makeSubscription();
        $payload = $this->invoicePaidPayload('evt_dupe_1');

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();
        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();

        $this->assertSame(1, PaymentTransaction::where('gateway_transaction_id', 'in_test_1')->count());
    }

    public function test_payment_failed_marks_subscription_past_due_and_notifies(): void
    {
        Notification::fake();
        $subscription = $this->makeSubscription(['status' => 'active']);

        $payload = $this->invoicePaidPayload('evt_failed_1');
        $payload['type'] = 'invoice.payment_failed';

        $this->postJson(route('webhooks.stripe'), $payload)->assertOk();

        $subscription->refresh();
        $this->assertSame('past_due', $subscription->status);
        Notification::assertSentTo($subscription->user, BillingPaymentFailedNotification::class);
    }
}
