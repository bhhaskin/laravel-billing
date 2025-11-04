<?php

use Bhhaskin\Billing\Events\QuotaExceeded;
use Bhhaskin\Billing\Events\QuotaWarning;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\QuotaUsage;
use Bhhaskin\Billing\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;

test('can record usage for a quota', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 100);

    expect($user->getUsage('disk_space'))->toBe(100.0)
        ->and($user->quotaUsages)->toHaveCount(1)
        ->and($user->quotaUsages->first()->quota_key)->toBe('disk_space');
});

test('can set absolute usage for a quota', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['bandwidth' => 5000])->create();
    $user->subscribe($plan);

    $user->setUsage('bandwidth', 2500);

    expect($user->getUsage('bandwidth'))->toBe(2500.0);

    $user->setUsage('bandwidth', 1000);

    expect($user->getUsage('bandwidth'))->toBe(1000.0);
});

test('can decrement usage for a quota', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 500);
    $user->decrementUsage('disk_space', 100);

    expect($user->getUsage('disk_space'))->toBe(400.0);
});

test('usage cannot go below zero', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 100);
    $user->decrementUsage('disk_space', 200);

    expect($user->getUsage('disk_space'))->toBe(0.0);
});

test('can reset usage to zero', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 500);
    expect($user->getUsage('disk_space'))->toBe(500.0);

    $user->resetUsage('disk_space');
    expect($user->getUsage('disk_space'))->toBe(0.0);
});

test('can get remaining quota', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['websites' => 10])->create();
    $user->subscribe($plan);

    $user->recordUsage('websites', 3);

    expect($user->getRemainingQuota('websites'))->toBe(7.0);
});

test('remaining quota is unlimited when no limit is set', function () {
    $user = User::factory()->create();

    expect($user->getRemainingQuota('websites'))->toBe(PHP_FLOAT_MAX);
});

test('can check if over quota', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 500);
    expect($user->isOverQuota('disk_space'))->toBeFalse();

    $user->recordUsage('disk_space', 600);
    expect($user->isOverQuota('disk_space'))->toBeTrue();
});

test('is not over quota when no limit is set', function () {
    $user = User::factory()->create();

    $user->recordUsage('disk_space', 999999);

    expect($user->isOverQuota('disk_space'))->toBeFalse();
});

test('can get quota percentage', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 250);

    expect($user->getQuotaPercentage('disk_space'))->toBe(25.0);

    $user->recordUsage('disk_space', 750);

    expect($user->getQuotaPercentage('disk_space'))->toBe(100.0);
});

test('fires quota warning event at 80 percent threshold', function () {
    Event::fake([QuotaWarning::class, QuotaExceeded::class]);

    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 800);

    Event::assertDispatched(QuotaWarning::class, function ($event) use ($user) {
        return $event->billable->id === $user->id
            && $event->quotaKey === 'disk_space'
            && $event->currentUsage === 800.0
            && $event->limit === 1000
            && $event->thresholdPercentage === 80;
    });
});

test('fires quota warning event at 90 percent threshold', function () {
    Event::fake([QuotaWarning::class, QuotaExceeded::class]);

    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 900);

    Event::assertDispatched(QuotaWarning::class, function ($event) use ($user) {
        return $event->billable->id === $user->id
            && $event->quotaKey === 'disk_space'
            && $event->currentUsage === 900.0
            && $event->limit === 1000
            && $event->thresholdPercentage === 80;
    });

    Event::assertDispatched(QuotaWarning::class, function ($event) use ($user) {
        return $event->billable->id === $user->id
            && $event->quotaKey === 'disk_space'
            && $event->currentUsage === 900.0
            && $event->limit === 1000
            && $event->thresholdPercentage === 90;
    });
});

test('does not fire duplicate warning events', function () {
    Event::fake([QuotaWarning::class, QuotaExceeded::class]);

    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 800);
    Event::assertDispatchedTimes(QuotaWarning::class, 1);

    $user->recordUsage('disk_space', 50);
    Event::assertDispatchedTimes(QuotaWarning::class, 1); // Still 1, no new warnings
});

test('fires quota exceeded event', function () {
    Event::fake([QuotaWarning::class, QuotaExceeded::class]);

    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 1100);

    Event::assertDispatched(QuotaExceeded::class, function ($event) use ($user) {
        return $event->billable->id === $user->id
            && $event->quotaKey === 'disk_space'
            && $event->currentUsage === 1100.0
            && $event->limit === 1000
            && $event->overage === 100.0;
    });
});

test('records last exceeded timestamp', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 1100);

    $quotaUsage = $user->quotaUsages()->where('quota_key', 'disk_space')->first();

    expect($quotaUsage->last_exceeded_at)->not->toBeNull();
});

test('resets warnings when usage drops below limit after decrement', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    // Trigger warnings
    $user->recordUsage('disk_space', 900);

    $quotaUsage = $user->quotaUsages()->where('quota_key', 'disk_space')->first();
    expect($quotaUsage->warning_thresholds_triggered)->not->toBeEmpty();

    // Decrement below all thresholds
    $user->decrementUsage('disk_space', 400);

    $quotaUsage->refresh();
    expect($quotaUsage->warning_thresholds_triggered)->toBeEmpty();
});

test('can track multiple quota types for same user', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits([
        'disk_space' => 1000,
        'bandwidth' => 5000,
        'websites' => 10,
    ])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 500);
    $user->recordUsage('bandwidth', 2000);
    $user->recordUsage('websites', 3);

    expect($user->getUsage('disk_space'))->toBe(500.0)
        ->and($user->getUsage('bandwidth'))->toBe(2000.0)
        ->and($user->getUsage('websites'))->toBe(3.0)
        ->and($user->quotaUsages)->toHaveCount(3);
});

test('quota usage record has unique constraint per billable and quota key', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->withLimits(['disk_space' => 1000])->create();
    $user->subscribe($plan);

    $user->recordUsage('disk_space', 100);
    $user->recordUsage('disk_space', 200);

    // Should still only have one quota usage record
    expect($user->quotaUsages)->toHaveCount(1)
        ->and($user->getUsage('disk_space'))->toBe(300.0);
});

test('can use quota usage factory', function () {
    $user = User::factory()->create();

    $quotaUsage = QuotaUsage::factory()
        ->forBillable($user)
        ->forQuota('disk_space')
        ->withUsage(750)
        ->create();

    expect($quotaUsage->billable->id)->toBe($user->id)
        ->and($quotaUsage->quota_key)->toBe('disk_space')
        ->and($quotaUsage->current_usage)->toBe(750.0);
});
