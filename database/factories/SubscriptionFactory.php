<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $start = now();
        $end = $start->copy()->addMonth();

        return [
            'customer_id' => Customer::factory(),
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => $start,
            'current_period_end' => $end,
            'failed_payment_count' => 0,
            'metadata' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    public function trialing(int $days = 14): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Subscription::STATUS_PAST_DUE,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Subscription::STATUS_CANCELED,
            'canceled_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Subscription::STATUS_CANCELED,
            'canceled_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Subscription::STATUS_SUSPENDED,
        ]);
    }

    public function withStripeId(): static
    {
        return $this->state(fn(array $attributes) => [
            'stripe_id' => 'sub_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'stripe_status' => $attributes['status'] ?? 'active',
        ]);
    }

    public function withFailedPayments(int $count = 1): static
    {
        return $this->state(fn(array $attributes) => [
            'failed_payment_count' => $count,
            'last_failed_payment_at' => now()->subDays($count),
            'status' => Subscription::STATUS_PAST_DUE,
        ]);
    }

    public function withWorkspace(int $workspaceId): static
    {
        return $this->state(fn(array $attributes) => [
            'workspace_id' => $workspaceId,
        ]);
    }
}
