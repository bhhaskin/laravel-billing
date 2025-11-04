<?php

use Bhhaskin\Billing\Models\Plan;

test('can create a plan', function () {
    $plan = Plan::factory()->create([
        'name' => 'Test Plan',
        'price' => 9.99,
    ]);

    expect($plan->name)->toBe('Test Plan')
        ->and($plan->price)->toBe('9.99')
        ->and($plan->uuid)->not->toBeNull();
});

test('can retrieve active plans', function () {
    Plan::factory()->create(['is_active' => true]);
    Plan::factory()->create(['is_active' => true]);
    Plan::factory()->create(['is_active' => false]);

    $activePlans = Plan::active()->get();

    expect($activePlans)->toHaveCount(2);
});

test('plan has limits', function () {
    $plan = Plan::factory()->withLimits([
        'websites' => 5,
        'storage_gb' => 100,
    ])->create();

    expect($plan->hasLimit('websites'))->toBeTrue()
        ->and($plan->getLimit('websites'))->toBe(5)
        ->and($plan->hasLimit('bandwidth'))->toBeFalse();
});

test('plan has features', function () {
    $plan = Plan::factory()->withFeatures([
        'ssl_certificate',
        'priority_support',
    ])->create();

    expect($plan->hasFeature('ssl_certificate'))->toBeTrue()
        ->and($plan->hasFeature('basic_support'))->toBeFalse();
});
