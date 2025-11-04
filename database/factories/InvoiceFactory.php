<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => Invoice::STATUS_DRAFT,
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'currency' => config('billing.currency', 'usd'),
            'notes' => null,
            'metadata' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Invoice::STATUS_DRAFT,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Invoice::STATUS_OPEN,
            'due_date' => now()->addDays(30),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Invoice::STATUS_VOID,
            'voided_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => Invoice::STATUS_OPEN,
            'due_date' => now()->subDays(10),
        ]);
    }

    public function withAmount(float $amount): static
    {
        return $this->state(fn(array $attributes) => [
            'subtotal' => $amount,
            'total' => $amount,
        ]);
    }

    public function withTax(float $tax): static
    {
        return $this->state(fn(array $attributes) => [
            'tax' => $tax,
            'total' => ($attributes['subtotal'] ?? 0) + $tax,
        ]);
    }

    public function withStripeId(): static
    {
        return $this->state(fn(array $attributes) => [
            'stripe_id' => 'in_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }
}
