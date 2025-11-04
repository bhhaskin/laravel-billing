<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\QuotaUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotaUsageFactory extends Factory
{
    protected $model = QuotaUsage::class;

    public function definition(): array
    {
        // Get the billable model class from config
        $billableModelClass = config('billing.billable_model', 'App\\Models\\User');

        // Try to create a billable model instance
        $billable = null;
        if (class_exists($billableModelClass) && method_exists($billableModelClass, 'factory')) {
            $billable = $billableModelClass::factory()->create();
        }

        return [
            'billable_type' => $billable ? get_class($billable) : null,
            'billable_id' => $billable ? $billable->id : null,
            'quota_key' => $this->faker->randomElement(['disk_space', 'bandwidth', 'websites', 'databases']),
            'current_usage' => $this->faker->numberBetween(0, 1000),
            'warning_thresholds_triggered' => [],
            'last_exceeded_at' => null,
        ];
    }

    public function forBillable($billable): static
    {
        return $this->state(fn(array $attributes) => [
            'billable_type' => get_class($billable),
            'billable_id' => $billable->getKey(),
        ]);
    }

    public function forQuota(string $quotaKey): static
    {
        return $this->state(fn(array $attributes) => [
            'quota_key' => $quotaKey,
        ]);
    }

    public function withUsage(float $usage): static
    {
        return $this->state(fn(array $attributes) => [
            'current_usage' => $usage,
        ]);
    }

    public function exceeded(): static
    {
        return $this->state(fn(array $attributes) => [
            'last_exceeded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function withWarningsTriggered(array $thresholds = [80, 90]): static
    {
        return $this->state(fn(array $attributes) => [
            'warning_thresholds_triggered' => $thresholds,
        ]);
    }
}
