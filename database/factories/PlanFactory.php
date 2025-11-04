<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        $price = $this->faker->randomFloat(2, 5, 500);

        return [
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'price' => $price,
            'interval' => $this->faker->randomElement([Plan::INTERVAL_MONTHLY, Plan::INTERVAL_YEARLY]),
            'interval_count' => 1,
            'type' => Plan::TYPE_PLAN,
            'requires_plan' => false,
            'is_active' => true,
            'trial_period_days' => 0,
            'grace_period_days' => 0,
            'cancellation_behavior' => Plan::CANCELLATION_END_OF_PERIOD,
            'change_behavior' => Plan::CHANGE_IMMEDIATE,
            'prorate_changes' => true,
            'prorate_cancellations' => false,
            'features' => [],
            'limits' => [],
            'metadata' => null,
            'sort_order' => 0,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn(array $attributes) => [
            'interval' => Plan::INTERVAL_MONTHLY,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn(array $attributes) => [
            'interval' => Plan::INTERVAL_YEARLY,
        ]);
    }

    public function addon(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Plan::TYPE_ADDON,
        ]);
    }

    public function requiresPlan(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Plan::TYPE_ADDON,
            'requires_plan' => true,
        ]);
    }

    public function standalone(): static
    {
        return $this->state(fn(array $attributes) => [
            'requires_plan' => false,
        ]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn(array $attributes) => [
            'trial_period_days' => $days,
        ]);
    }

    public function withGracePeriod(int $days = 3): static
    {
        return $this->state(fn(array $attributes) => [
            'grace_period_days' => $days,
        ]);
    }

    public function withFeatures(array $features): static
    {
        return $this->state(fn(array $attributes) => [
            'features' => $features,
        ]);
    }

    public function withLimits(array $limits): static
    {
        return $this->state(fn(array $attributes) => [
            'limits' => $limits,
        ]);
    }

    public function withStripeIds(): static
    {
        return $this->state(fn(array $attributes) => [
            'stripe_product_id' => 'prod_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
            'stripe_price_id' => 'price_' . $this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
