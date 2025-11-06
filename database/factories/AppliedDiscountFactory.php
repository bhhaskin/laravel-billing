<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\AppliedDiscount;
use Bhhaskin\Billing\Models\Discount;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppliedDiscountFactory extends Factory
{
    protected $model = AppliedDiscount::class;

    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'discount_id' => Discount::factory(),
            'applied_at' => now(),
            'expires_at' => null,
            'total_uses' => 0,
        ];
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
     * Expired applied discount
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Active applied discount
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * With usage count
     */
    public function withUses(int $uses): static
    {
        return $this->state(fn (array $attributes) => [
            'total_uses' => $uses,
        ]);
    }
}
