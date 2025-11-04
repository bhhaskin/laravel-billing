<?php

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Tests\Fixtures\User;

test('can create a subscription', function () {
    $customer = Customer::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = Subscription::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $subscription->addItem($plan);

    expect($subscription->customer_id)->toBe($customer->id)
        ->and($subscription->items)->toHaveCount(1)
        ->and($subscription->uuid)->not->toBeNull();
});

test('subscription is active', function () {
    $subscription = Subscription::factory()->active()->create();

    expect($subscription->isActive())->toBeTrue()
        ->and($subscription->hasEnded())->toBeFalse();
});

test('subscription is trialing', function () {
    $subscription = Subscription::factory()->trialing()->create();

    expect($subscription->isTrialing())->toBeTrue();
});

test('subscription has plans', function () {
    $subscription = Subscription::factory()->create();
    $plan1 = Plan::factory()->create();
    $plan2 = Plan::factory()->addon()->create();

    $subscription->addItem($plan1);
    $subscription->addItem($plan2);

    expect($subscription->hasPlans())->toBeTrue()
        ->and($subscription->hasPlan($plan1))->toBeTrue()
        ->and($subscription->items)->toHaveCount(2);
});

test('user can subscribe to plan', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $subscription = $user->subscribe($plan);

    expect($subscription)->toBeInstanceOf(Subscription::class)
        ->and($user->hasActiveSubscription())->toBeTrue()
        ->and($user->subscribedToPlan($plan))->toBeTrue();
});

test('user can get combined limits', function () {
    $user = User::factory()->create();
    $plan1 = Plan::factory()->withLimits(['websites' => 5, 'storage_gb' => 100])->create();
    $plan2 = Plan::factory()->withLimits(['websites' => 10, 'storage_gb' => 50])->create();

    $user->subscribe($plan1);
    $user->subscribe($plan2);

    $limits = $user->getCombinedLimits();

    expect($limits['websites'])->toBe(15)
        ->and($limits['storage_gb'])->toBe(150);
});
