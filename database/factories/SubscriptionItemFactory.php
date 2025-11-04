<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Bhhaskin\Billing\Models\SubscriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionItemFactory extends Factory
{
    protected $model = SubscriptionItem::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'plan_id' => Plan::factory(),
            'quantity' => 1,
            'metadata' => null,
        ];
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(fn(array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    public function withStripeId(): static
    {
        return $this->state(fn(array $attributes) => [
            'stripe_id' => 'si_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn(array $attributes) => [
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn(array $attributes) => [
            'ends_at' => now()->subDay(),
        ]);
    }
}
