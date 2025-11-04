<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\SubscriptionItem;
use Bhhaskin\Billing\Models\UsageRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    public function definition(): array
    {
        return [
            'subscription_item_id' => SubscriptionItem::factory(),
            'quantity' => $this->faker->numberBetween(1, 1000),
            'action' => UsageRecord::ACTION_SET,
            'timestamp' => now(),
            'reported_to_stripe' => false,
            'metadata' => null,
        ];
    }

    public function set(): static
    {
        return $this->state(fn(array $attributes) => [
            'action' => UsageRecord::ACTION_SET,
        ]);
    }

    public function increment(): static
    {
        return $this->state(fn(array $attributes) => [
            'action' => UsageRecord::ACTION_INCREMENT,
        ]);
    }

    public function reported(): static
    {
        return $this->state(fn(array $attributes) => [
            'reported_to_stripe' => true,
        ]);
    }

    public function withTimestamp(\DateTime $timestamp): static
    {
        return $this->state(fn(array $attributes) => [
            'timestamp' => $timestamp,
        ]);
    }
}
