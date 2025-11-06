<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'invoice_id' => Invoice::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'currency' => 'usd',
            'status' => Refund::STATUS_PENDING,
            'reason' => Refund::REASON_REQUESTED_BY_CUSTOMER,
            'description' => $this->faker->sentence,
            'notes' => null,
            'metadata' => null,
            'processed_at' => null,
            'failure_reason' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Refund::STATUS_SUCCEEDED,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Refund::STATUS_FAILED,
            'processed_at' => now(),
            'failure_reason' => $this->faker->sentence,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Refund::STATUS_PENDING,
            'processed_at' => null,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Refund::STATUS_CANCELED,
            'processed_at' => now(),
        ]);
    }

    public function withStripeId(): static
    {
        return $this->state(fn (array $attributes) => [
            'stripe_refund_id' => 're_' . $this->faker->unique()->regexify('[A-Za-z0-9]{24}'),
        ]);
    }
}
