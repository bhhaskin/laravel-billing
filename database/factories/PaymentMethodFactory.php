<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'stripe_id' => 'pm_' . $this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
            'type' => PaymentMethod::TYPE_CARD,
            'brand' => $this->faker->randomElement(['visa', 'mastercard', 'amex', 'discover']),
            'last_four' => $this->faker->numerify('####'),
            'exp_month' => str_pad($this->faker->numberBetween(1, 12), 2, '0', STR_PAD_LEFT),
            'exp_year' => $this->faker->numberBetween(now()->year, now()->year + 10),
            'is_default' => false,
            'metadata' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function card(string $brand = 'visa'): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => PaymentMethod::TYPE_CARD,
            'brand' => $brand,
        ]);
    }

    public function bankAccount(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => PaymentMethod::TYPE_BANK_ACCOUNT,
            'brand' => null,
            'exp_month' => null,
            'exp_year' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'exp_month' => '01',
            'exp_year' => now()->subYear()->year,
        ]);
    }
}
