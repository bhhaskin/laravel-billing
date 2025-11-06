<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('??????')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
            'type' => 'percentage',
            'value' => $this->faker->numberBetween(10, 50),
            'currency' => null,
            'applies_to' => 'all',
            'applicable_plan_ids' => null,
            'duration' => 'once',
            'duration_in_months' => null,
            'max_redemptions' => null,
            'redemptions_count' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'is_active' => true,
        ];
    }

    /**
     * Percentage discount
     */
    public function percentage(float $value = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'percentage',
            'value' => $value,
            'currency' => null,
        ]);
    }

    /**
     * Fixed amount discount
     */
    public function fixed(float $value = 10, string $currency = 'usd'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'value' => $value,
            'currency' => $currency,
        ]);
    }

    /**
     * One-time discount
     */
    public function once(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => 'once',
            'duration_in_months' => null,
        ]);
    }

    /**
     * Forever discount
     */
    public function forever(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => 'forever',
            'duration_in_months' => null,
        ]);
    }

    /**
     * Repeating discount for N months
     */
    public function repeating(int $months = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => 'repeating',
            'duration_in_months' => $months,
        ]);
    }

    /**
     * Admin-only discount (no code)
     */
    public function adminOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => null,
        ]);
    }

    /**
     * With redemption limit
     */
    public function withMaxRedemptions(int $max): static
    {
        return $this->state(fn (array $attributes) => [
            'max_redemptions' => $max,
        ]);
    }

    /**
     * With expiration date
     */
    public function expiresAt(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $date,
        ]);
    }

    /**
     * Expired discount
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Inactive discount
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Active discount
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * Applies to specific plans
     */
    public function forPlans(array $planUuids): static
    {
        return $this->state(fn (array $attributes) => [
            'applies_to' => 'specific_plans',
            'applicable_plan_ids' => $planUuids,
        ]);
    }

    /**
     * Fully redeemed discount
     */
    public function fullyRedeemed(int $maxRedemptions = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'max_redemptions' => $maxRedemptions,
            'redemptions_count' => $maxRedemptions,
        ]);
    }
}
