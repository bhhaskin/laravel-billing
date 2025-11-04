<?php

namespace Bhhaskin\Billing\Database\Factories;

use Bhhaskin\Billing\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

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
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'metadata' => null,
        ];
    }

    public function forBillable($billable): static
    {
        return $this->state(fn(array $attributes) => [
            'billable_type' => get_class($billable),
            'billable_id' => $billable->getKey(),
            'email' => $billable->email ?? $attributes['email'],
            'name' => $billable->name ?? $attributes['name'],
        ]);
    }

    public function withStripeId(): static
    {
        return $this->state(fn(array $attributes) => [
            'stripe_id' => 'cus_' . $this->faker->unique()->regexify('[A-Za-z0-9]{14}'),
        ]);
    }

    public function withWorkspace(int $workspaceId): static
    {
        return $this->state(fn(array $attributes) => [
            'workspace_id' => $workspaceId,
        ]);
    }
}
