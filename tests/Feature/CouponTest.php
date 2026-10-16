<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): AdminUser
    {
        return $this->createSuperAdmin();
    }

    public function test_admin_can_list_coupons(): void
    {
        Coupon::factory()->count(3)->create();

        $this->actingAs($this->adminUser(), 'admin')
            ->get(route('admin.coupons.index'))
            ->assertOk();
    }

    public function test_admin_can_create_coupon(): void
    {
        $this->actingAs($this->adminUser(), 'admin')
            ->post(route('admin.coupons.store'), [
                'code'             => 'SAVE10',
                'kind'             => 'percent',
                'amount'           => 10,
                'duration'         => 'once',
                'max_redemptions'  => 100,
                'enabled'          => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('coupons', ['code' => 'SAVE10', 'kind' => 'percent']);
    }

    public function test_admin_can_delete_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->actingAs($this->adminUser(), 'admin')
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect();

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_coupon_model_validates_expiry(): void
    {
        $expired = Coupon::factory()->create([
            'enabled'    => true,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($expired->isValid());
    }

    public function test_coupon_model_calculates_discount(): void
    {
        $coupon = Coupon::factory()->create([
            'kind'    => 'percent',
            'amount'  => 20,
            'enabled' => true,
        ]);

        $this->assertEquals(200, $coupon->discountCents(1000));
    }
}
