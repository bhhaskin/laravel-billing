<?php

namespace Bhhaskin\Billing\Tests\Feature;

use Bhhaskin\Billing\Models\Discount;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Tests\Fixtures\User;
use Bhhaskin\Billing\Tests\TestCase;

class DiscountTest extends TestCase
{
    /** @test */
    public function it_can_create_a_discount_code()
    {
        $discount = Discount::factory()->create([
            'code' => 'SUMMER2025',
            'name' => 'Summer Sale',
            'type' => 'percentage',
            'value' => 25,
        ]);

        $this->assertDatabaseHas('billing_discounts', [
            'code' => 'SUMMER2025',
            'name' => 'Summer Sale',
            'value' => 25,
        ]);
    }

    /** @test */
    public function it_validates_discount_codes()
    {
        $discount = Discount::factory()->active()->create([
            'code' => 'VALID',
        ]);

        $this->assertTrue($discount->isValid());
    }

    /** @test */
    public function it_invalidates_expired_discounts()
    {
        $discount = Discount::factory()->expired()->create();

        $this->assertFalse($discount->isValid());
    }

    /** @test */
    public function it_invalidates_fully_redeemed_discounts()
    {
        $discount = Discount::factory()->fullyRedeemed(10)->create();

        $this->assertFalse($discount->isValid());
    }

    /** @test */
    public function it_calculates_percentage_discounts()
    {
        $discount = Discount::factory()->percentage(20)->create();

        $discountAmount = $discount->calculateDiscount(100);

        $this->assertEquals(20, $discountAmount);
    }

    /** @test */
    public function it_calculates_fixed_discounts()
    {
        $discount = Discount::factory()->fixed(15, 'usd')->create();

        $discountAmount = $discount->calculateDiscount(100, 'usd');

        $this->assertEquals(15, $discountAmount);
    }

    /** @test */
    public function it_applies_discount_to_subscription()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 100]);
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->percentage(20)->create();

        $appliedDiscount = $subscription->applyDiscount($discount);

        $this->assertDatabaseHas('billing_applied_discounts', [
            'subscription_id' => $subscription->id,
            'discount_id' => $discount->id,
        ]);

        $this->assertEquals(1, $discount->fresh()->redemptions_count);
    }

    /** @test */
    public function it_checks_if_discount_applies_to_plan()
    {
        $plan1 = Plan::factory()->create();
        $plan2 = Plan::factory()->create();

        $discount = Discount::factory()
            ->forPlans([$plan1->uuid])
            ->create();

        $this->assertTrue($discount->canApplyToPlan($plan1));
        $this->assertFalse($discount->canApplyToPlan($plan2));
    }

    /** @test */
    public function it_prevents_applying_same_discount_twice()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->create();

        $subscription->applyDiscount($discount);

        $this->expectException(\InvalidArgumentException::class);
        $subscription->applyDiscount($discount);
    }

    /** @test */
    public function it_removes_discount_from_subscription()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->create();
        $subscription->applyDiscount($discount);

        $subscription->removeDiscount($discount);

        $this->assertFalse($subscription->hasDiscount($discount));
    }

    /** @test */
    public function it_gets_active_discounts()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $discount1 = Discount::factory()->create();
        $discount2 = Discount::factory()->create();
        $discount3 = Discount::factory()->expired()->create();

        $subscription->applyDiscount($discount1);
        $subscription->applyDiscount($discount2);

        $activeDiscounts = $subscription->getActiveDiscounts();

        $this->assertCount(2, $activeDiscounts);
        $this->assertTrue($activeDiscounts->contains($discount1));
        $this->assertTrue($activeDiscounts->contains($discount2));
    }

    /** @test */
    public function it_calculates_discount_amount_for_subscription()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 100]);
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->percentage(20)->create();
        $subscription->applyDiscount($discount);

        $discountAmount = $subscription->calculateDiscountAmount(100);

        $this->assertEquals(20, $discountAmount);
    }

    /** @test */
    public function it_applies_admin_only_discount()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->adminOnly()->percentage(100)->create([
            'name' => 'Free for Staff',
        ]);

        $subscription->applyDiscount($discount);

        $this->assertTrue($subscription->hasDiscount($discount));
    }

    /** @test */
    public function it_stacks_multiple_discounts()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['price' => 100]);
        $subscription = $user->subscribe($plan);

        $discount1 = Discount::factory()->percentage(10)->create();
        $discount2 = Discount::factory()->percentage(5)->create();

        $subscription->applyDiscount($discount1);
        $subscription->applyDiscount($discount2);

        // First discount: 100 * 10% = 10
        // Second discount: (100 - 10) * 5% = 4.5
        // Total discount: 14.5
        $totalDiscount = $subscription->calculateDiscountAmount(100);

        $this->assertEquals(14.5, $totalDiscount);
    }

    /** @test */
    public function user_can_apply_discount_code()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->create(['code' => 'PROMO20']);

        $user->applyDiscountCode('PROMO20', $subscription);

        $this->assertTrue($subscription->hasDiscount($discount));
    }

    /** @test */
    public function admin_can_apply_discount_without_code()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $discount = Discount::factory()->adminOnly()->create([
            'name' => 'Admin Special Discount',
        ]);

        $user->applyAdminDiscount($discount, $subscription);

        $this->assertTrue($subscription->hasDiscount($discount));
    }
}
