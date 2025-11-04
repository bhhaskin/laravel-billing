<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\InvoiceItem;
use Bhhaskin\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 5, 100);
        $amount = $quantity * $unitPrice;

        return [
            'invoice_id' => Invoice::factory(),
            'plan_id' => Plan::factory(),
            'description' => $this->faker->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'is_proration' => false,
            'metadata' => null,
        ];
    }

    public function proration(): static
    {
        return $this->state(fn(array $attributes) => [
            'is_proration' => true,
            'period_start' => now()->subDays(15),
            'period_end' => now(),
        ]);
    }

    public function withPeriod(\DateTime $start, \DateTime $end): static
    {
        return $this->state(fn(array $attributes) => [
            'period_start' => $start,
            'period_end' => $end,
        ]);
    }
}
