<?php

namespace Tests\Unit;

use App\Models\Plan;
use PHPUnit\Framework\TestCase;

class PlanPricingTest extends TestCase
{
    public function test_it_adds_included_unified_connections_to_the_plan_price(): void
    {
        $plan = new Plan([
            'base_monthly_price_cents' => 10000,
            'base_yearly_price_cents' => 100000,
            'unified_connection_unit_price_cents' => 2500,
            'limits' => ['unified_connections' => 3],
        ]);

        $this->assertSame(17500, $plan->priceCentsForCycle('month'));
        $this->assertSame(190000, $plan->priceCentsForCycle('year'));
        $this->assertSame(7500, $plan->connectionCostCentsForCycle('month'));
        $this->assertSame(90000, $plan->connectionCostCentsForCycle('year'));
    }

    public function test_it_preserves_legacy_prices_and_treats_legacy_modules_as_enabled(): void
    {
        $plan = new Plan([
            'monthly_price_cents' => 9900,
            'yearly_price_cents' => 99000,
            'limits' => [],
            'modules' => null,
        ]);

        $this->assertSame(9900, $plan->priceCentsForCycle('month'));
        $this->assertSame(99000, $plan->priceCentsForCycle('year'));
        $this->assertTrue($plan->hasModule('inbox'));
    }

    public function test_it_disables_only_modules_not_included_in_the_matrix(): void
    {
        $plan = new Plan([
            'modules' => [
                'inbox' => true,
                'ads' => false,
            ],
        ]);

        $this->assertTrue($plan->hasModule('inbox'));
        $this->assertFalse($plan->hasModule('ads'));
        $this->assertFalse($plan->hasModule('ai'));
    }
}
