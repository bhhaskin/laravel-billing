<?php

namespace Bhhaskin\Billing\Tests\Feature;

use Bhhaskin\Billing\Events\PlanChanged;
use Bhhaskin\Billing\Events\PlanChangeScheduled;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Tests\Fixtures\User;
use Bhhaskin\Billing\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class PlanChangeTest extends TestCase
{
    /** @test */
    public function it_can_change_subscription_plan()
    {
        $user = User::factory()->create();
        $starterPlan = Plan::factory()->create(['name' => 'Starter', 'price' => 10.00]);
        $proPlan = Plan::factory()->create(['name' => 'Professional', 'price' => 25.00]);

        $subscription = $user->subscribe($starterPlan);
        $subscription->changePlan($proPlan);

        $this->assertEquals($proPlan->id, $subscription->fresh()->getCurrentPlan()->id);
        $this->assertEquals($starterPlan->id, $subscription->fresh()->previous_plan_id);
        $this->assertNotNull($subscription->fresh()->plan_changed_at);
    }

    /** @test */
    public function it_fires_event_when_plan_changed()
    {
        $user = User::factory()->create();
        $plan1 = Plan::factory()->create(['price' => 10.00]);
        $plan2 = Plan::factory()->create(['price' => 20.00]);

        $subscription = $user->subscribe($plan1);

        Event::fake([PlanChanged::class]);

        $subscription->changePlan($plan2);

        Event::assertDispatched(PlanChanged::class);
    }

    /** @test */
    public function it_prevents_changing_to_same_plan()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create();
        $subscription = $user->subscribe($plan);

        $this->expectException(\InvalidArgumentException::class);

        $subscription->changePlan($plan);
    }

    /** @test */
    public function it_can_schedule_plan_change()
    {
        $user = User::factory()->create();
        $currentPlan = Plan::factory()->create();
        $newPlan = Plan::factory()->create();

        $subscription = $user->subscribe($currentPlan);
        $subscription->changePlan($newPlan, ['schedule' => true]);

        $this->assertEquals($newPlan->id, $subscription->scheduled_plan_id);
        $this->assertNotNull($subscription->plan_change_scheduled_for);
        $this->assertEquals($currentPlan->id, $subscription->getCurrentPlan()->id);
    }

    /** @test */
    public function it_fires_event_when_plan_change_scheduled()
    {
        $user = User::factory()->create();
        $plan1 = Plan::factory()->create();
        $plan2 = Plan::factory()->create();

        $subscription = $user->subscribe($plan1);

        Event::fake([PlanChangeScheduled::class]);

        $subscription->changePlan($plan2, ['schedule' => true]);

        Event::assertDispatched(PlanChangeScheduled::class);
    }

    /** @test */
    public function it_can_cancel_scheduled_plan_change()
    {
        $user = User::factory()->create();
        $currentPlan = Plan::factory()->create();
        $newPlan = Plan::factory()->create();

        $subscription = $user->subscribe($currentPlan);
        $subscription->changePlan($newPlan, ['schedule' => true]);
        $subscription->cancelScheduledPlanChange();

        $this->assertNull($subscription->fresh()->scheduled_plan_id);
        $this->assertNull($subscription->fresh()->plan_change_scheduled_for);
    }

    /** @test */
    public function it_detects_if_has_scheduled_plan_change()
    {
        $user = User::factory()->create();
        $plan1 = Plan::factory()->create();
        $plan2 = Plan::factory()->create();

        $subscription = $user->subscribe($plan1);

        $this->assertFalse($subscription->hasScheduledPlanChange());

        $subscription->changePlan($plan2, ['schedule' => true]);

        $this->assertTrue($subscription->hasScheduledPlanChange());
    }

    /** @test */
    public function it_applies_scheduled_plan_change()
    {
        $user = User::factory()->create();
        $starterPlan = Plan::factory()->create(['name' => 'Starter']);
        $proPlan = Plan::factory()->create(['name' => 'Pro']);

        $subscription = $user->subscribe($starterPlan);

        // Schedule change for past date
        $subscription->update([
            'scheduled_plan_id' => $proPlan->id,
            'plan_change_scheduled_for' => now()->subDay(),
        ]);

        $result = $subscription->applyScheduledPlanChange();

        $this->assertTrue($result);
        $this->assertEquals($proPlan->id, $subscription->fresh()->getCurrentPlan()->id);
        $this->assertNull($subscription->fresh()->scheduled_plan_id);
    }

    /** @test */
    public function it_doesnt_apply_future_scheduled_change()
    {
        $user = User::factory()->create();
        $plan1 = Plan::factory()->create();
        $plan2 = Plan::factory()->create();

        $subscription = $user->subscribe($plan1);

        $subscription->update([
            'scheduled_plan_id' => $plan2->id,
            'plan_change_scheduled_for' => now()->addDays(7),
        ]);

        $result = $subscription->applyScheduledPlanChange();

        $this->assertFalse($result);
        $this->assertEquals($plan1->id, $subscription->fresh()->getCurrentPlan()->id);
    }

    /** @test */
    public function it_previews_plan_change_costs()
    {
        $user = User::factory()->create();
        $starterPlan = Plan::factory()->create(['name' => 'Starter', 'price' => 10.00]);
        $proPlan = Plan::factory()->create(['name' => 'Professional', 'price' => 25.00]);

        $subscription = $user->subscribe($starterPlan);
        $subscription->update([
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->addDays(15),
        ]);

        $preview = $subscription->previewPlanChange($proPlan);

        $this->assertArrayHasKey('current_plan', $preview);
        $this->assertArrayHasKey('new_plan', $preview);
        $this->assertArrayHasKey('proration', $preview);
        $this->assertArrayHasKey('period', $preview);

        $this->assertEquals($starterPlan->id, $preview['current_plan']['id']);
        $this->assertEquals($proPlan->id, $preview['new_plan']['id']);
        $this->assertTrue($preview['proration']['is_upgrade']);
        $this->assertFalse($preview['proration']['is_downgrade']);
    }

    /** @test */
    public function it_detects_upgrade_vs_downgrade()
    {
        $user = User::factory()->create();
        $cheapPlan = Plan::factory()->create(['price' => 10.00]);
        $expensivePlan = Plan::factory()->create(['price' => 50.00]);

        $subscription = $user->subscribe($cheapPlan);

        $this->assertTrue($subscription->isUpgrade($expensivePlan));
        $this->assertFalse($subscription->isDowngrade($expensivePlan));

        $subscription2 = $user->subscribe($expensivePlan);

        $this->assertTrue($subscription2->isDowngrade($cheapPlan));
        $this->assertFalse($subscription2->isUpgrade($cheapPlan));
    }

    /** @test */
    public function it_gets_current_plan()
    {
        $user = User::factory()->create();
        $plan = Plan::factory()->create(['name' => 'Test Plan']);

        $subscription = $user->subscribe($plan);

        $currentPlan = $subscription->getCurrentPlan();

        $this->assertNotNull($currentPlan);
        $this->assertEquals($plan->id, $currentPlan->id);
    }

    /** @test */
    public function preview_calculates_proration_correctly()
    {
        $user = User::factory()->create();
        $plan10 = Plan::factory()->create(['price' => 10.00]);
        $plan20 = Plan::factory()->create(['price' => 20.00]);

        $subscription = $user->subscribe($plan10);

        // Set to halfway through period
        $subscription->update([
            'current_period_start' => now()->subDays(15),
            'current_period_end' => now()->addDays(15),
        ]);
        $subscription->refresh(); // Reload the model with updated dates

        $preview = $subscription->previewPlanChange($plan20);

        // Halfway through: ~5 credit from old, ~10 charge for new = ~5 due
        $this->assertGreaterThan(0, $preview['proration']['amount_due']);
        $this->assertEquals(5.00, $preview['proration']['unused_amount']);
        $this->assertEquals(10.00, $preview['proration']['charge_for_new']);
    }
}
