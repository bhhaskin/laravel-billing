<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\CustomerCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerCreditFactory extends Factory
{
    protected $model = CustomerCredit::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 5, 100);

        return [
            'customer_id' => Customer::factory(),
            'type' => CustomerCredit::TYPE_MANUAL_ADJUSTMENT,
            'amount' => $amount,
            'currency' => 'usd',
            'balance_before' => 0,
            'balance_after' => $amount,
            'description' => $this->faker->sentence,
            'notes' => null,
            'metadata' => null,
            'invoice_id' => null,
            'refund_id' => null,
            'expires_at' => null,
            'is_expired' => false,
        ];
    }

    public function promotional(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomerCredit::TYPE_PROMOTIONAL,
            'description' => 'Promotional credit',
        ]);
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomerCredit::TYPE_REFUND,
            'description' => 'Refund credit',
        ]);
    }

    public function invoicePayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomerCredit::TYPE_INVOICE_PAYMENT,
            'amount' => -abs($attributes['amount']), // Make negative
            'description' => 'Applied to invoice',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
            'is_expired' => true,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
            'is_expired' => false,
        ]);
    }

    public function withExpiration(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $date,
        ]);
    }
}
