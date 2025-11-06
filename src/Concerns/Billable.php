<?php

namespace Bhhaskin\Billing\Concerns;

use Bhhaskin\Billing\Events\QuotaExceeded;
use Bhhaskin\Billing\Events\QuotaWarning;
use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\Discount;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\QuotaUsage;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Trait Billable
 *
 * Add billing capabilities to any model (typically User).
 */
trait Billable
{
    /**
     * Get the customer record for this billable model.
     */
    public function customer(): MorphOne
    {
        return $this->morphOne(Customer::class, 'billable');
    }

    /**
     * Get or create a customer record for this billable model.
     */
    public function getOrCreateCustomer(array $attributes = []): Customer
    {
        if ($customer = $this->customer) {
            return $customer;
        }

        // Whitelist allowed attributes to prevent mass assignment vulnerabilities
        $allowedAttributes = \Illuminate\Support\Arr::only($attributes, [
            'workspace_id',
            'stripe_id',
            'metadata',
        ]);

        return $this->customer()->create(array_merge([
            'email' => $this->email ?? null,
            'name' => $this->name ?? null,
        ], $allowedAttributes));
    }

    /**
     * Determine if the billable model has a customer record.
     */
    public function hasCustomer(): bool
    {
        return $this->customer()->exists();
    }

    /**
     * Get all subscriptions for this billable model.
     */
    public function subscriptions()
    {
        return $this->hasManyThrough(
            Subscription::class,
            Customer::class,
            'billable_id',
            'customer_id',
            'id',
            'id'
        )->where('billable_type', static::class);
    }

    /**
     * Get active subscriptions.
     */
    public function activeSubscriptions()
    {
        return $this->subscriptions()->whereIn('status', [
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_TRIALING,
        ]);
    }

    /**
     * Determine if the billable model has any active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscriptions()->exists();
    }

    /**
     * Determine if the billable model is subscribed to a specific plan.
     */
    public function subscribedToPlan(Plan|int|string $plan): bool
    {
        $planId = $plan instanceof Plan ? $plan->id : $plan;

        return $this->activeSubscriptions()
            ->whereHas('items', function ($query) use ($planId) {
                $query->where('plan_id', $planId);
            })
            ->exists();
    }

    /**
     * Get all invoices for this billable model.
     */
    public function invoices()
    {
        return $this->hasManyThrough(
            Invoice::class,
            Customer::class,
            'billable_id',
            'customer_id',
            'id',
            'id'
        )->where('billable_type', static::class);
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribe(Plan $plan, array $options = []): Subscription
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($plan, $options) {
            $customer = $this->getOrCreateCustomer();

            $subscription = $customer->subscriptions()->create([
                'status' => $options['status'] ?? Subscription::STATUS_ACTIVE,
                'trial_ends_at' => $options['trial_ends_at'] ?? null,
                'current_period_start' => $options['current_period_start'] ?? now(),
                'current_period_end' => $options['current_period_end'] ?? now()->addMonth(),
            ]);

            $subscription->addItem($plan, $options['quantity'] ?? 1);

            return $subscription;
        });
    }

    /**
     * Get the combined limits from all active subscriptions.
     */
    public function getCombinedLimits(): array
    {
        $limits = [];

        $this->activeSubscriptions()
            ->with('items.plan')
            ->get()
            ->flatMap(fn($subscription) => $subscription->items)
            ->each(function ($item) use (&$limits) {
                if (! $item->plan || ! $item->plan->limits) {
                    return;
                }

                foreach ($item->plan->limits as $key => $value) {
                    if (! isset($limits[$key])) {
                        $limits[$key] = 0;
                    }
                    $limits[$key] += $value * $item->quantity;
                }
            });

        return $limits;
    }

    /**
     * Get a specific limit value across all active subscriptions.
     */
    public function getLimit(string $key, $default = null)
    {
        return $this->getCombinedLimits()[$key] ?? $default;
    }

    /**
     * Check if the billable model has a specific feature from any active subscription.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->activeSubscriptions()
            ->with('items.plan')
            ->get()
            ->flatMap(fn($subscription) => $subscription->items)
            ->contains(function ($item) use ($feature) {
                return $item->plan && $item->plan->hasFeature($feature);
            });
    }

    /**
     * Get all features from active subscriptions.
     */
    public function getFeatures(): array
    {
        return $this->activeSubscriptions()
            ->with('items.plan')
            ->get()
            ->flatMap(fn($subscription) => $subscription->items)
            ->filter(fn($item) => $item->plan && $item->plan->features)
            ->flatMap(fn($item) => $item->plan->features)
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Apply a discount code to a subscription.
     */
    public function applyDiscountCode(string $code, ?Subscription $subscription = null): void
    {
        $discount = Discount::byCode($code)->active()->firstOrFail();

        // If no subscription specified, apply to the first active subscription
        if (! $subscription) {
            $subscription = $this->activeSubscriptions()->first();

            if (! $subscription) {
                throw new \InvalidArgumentException('No active subscription found');
            }
        }

        $subscription->applyDiscount($discount);
    }

    /**
     * Apply an admin discount to a subscription by discount ID.
     */
    public function applyAdminDiscount(Discount $discount, ?Subscription $subscription = null): void
    {
        // If no subscription specified, apply to the first active subscription
        if (! $subscription) {
            $subscription = $this->activeSubscriptions()->first();

            if (! $subscription) {
                throw new \InvalidArgumentException('No active subscription found');
            }
        }

        $subscription->applyDiscount($discount);
    }

    /**
     * Get all active discounts across all subscriptions.
     */
    public function getAllActiveDiscounts()
    {
        return $this->activeSubscriptions()
            ->with('appliedDiscounts.discount')
            ->get()
            ->flatMap(fn($subscription) => $subscription->getActiveDiscounts())
            ->unique('id');
    }

    /**
     * Get all quota usage records for this billable model.
     */
    public function quotaUsages(): MorphMany
    {
        return $this->morphMany(QuotaUsage::class, 'billable');
    }

    /**
     * Get or create a quota usage record for a specific quota key.
     */
    protected function getOrCreateQuotaUsage(string $quotaKey): QuotaUsage
    {
        return $this->quotaUsages()->firstOrCreate(
            ['quota_key' => $quotaKey],
            ['current_usage' => 0]
        );
    }

    /**
     * Get the current usage for a specific quota.
     */
    public function getUsage(string $quotaKey): float
    {
        $usage = $this->quotaUsages()->where('quota_key', $quotaKey)->first();

        return $usage ? $usage->current_usage : 0;
    }

    /**
     * Set the absolute usage for a specific quota.
     */
    public function setUsage(string $quotaKey, float $amount): void
    {
        $quotaUsage = $this->getOrCreateQuotaUsage($quotaKey);
        $quotaUsage->update(['current_usage' => max(0, $amount)]);

        $this->checkQuotaThresholds($quotaKey, $quotaUsage);
    }

    /**
     * Record (increment) usage for a specific quota and check thresholds.
     */
    public function recordUsage(string $quotaKey, float $amount): void
    {
        $quotaUsage = $this->getOrCreateQuotaUsage($quotaKey);
        $newUsage = $quotaUsage->current_usage + $amount;
        $quotaUsage->update(['current_usage' => max(0, $newUsage)]);

        $this->checkQuotaThresholds($quotaKey, $quotaUsage);
    }

    /**
     * Decrement usage for a specific quota.
     */
    public function decrementUsage(string $quotaKey, float $amount): void
    {
        $quotaUsage = $this->getOrCreateQuotaUsage($quotaKey);
        $newUsage = $quotaUsage->current_usage - $amount;
        $quotaUsage->update(['current_usage' => max(0, $newUsage)]);

        // Reset warnings if usage drops below all thresholds
        $limit = $this->getLimit($quotaKey);
        if ($limit && $newUsage < $limit) {
            $quotaUsage->resetWarnings();
            $quotaUsage->update(['last_exceeded_at' => null]);
        }
    }

    /**
     * Reset usage for a specific quota to zero.
     */
    public function resetUsage(string $quotaKey): void
    {
        $quotaUsage = $this->quotaUsages()->where('quota_key', $quotaKey)->first();

        if ($quotaUsage) {
            $quotaUsage->update([
                'current_usage' => 0,
                'warning_thresholds_triggered' => [],
                'last_exceeded_at' => null,
            ]);
        }
    }

    /**
     * Get the remaining quota for a specific key.
     */
    public function getRemainingQuota(string $quotaKey): float
    {
        $limit = $this->getLimit($quotaKey);
        $usage = $this->getUsage($quotaKey);

        if ($limit === null) {
            return PHP_FLOAT_MAX; // unlimited
        }

        return max(0, $limit - $usage);
    }

    /**
     * Check if the billable model is over quota for a specific key.
     */
    public function isOverQuota(string $quotaKey): bool
    {
        $limit = $this->getLimit($quotaKey);

        if ($limit === null) {
            return false; // unlimited
        }

        return $this->getUsage($quotaKey) > $limit;
    }

    /**
     * Get the percentage of quota used for a specific key.
     */
    public function getQuotaPercentage(string $quotaKey): float
    {
        $limit = $this->getLimit($quotaKey);

        if ($limit === null || $limit == 0) {
            return 0;
        }

        $usage = $this->getUsage($quotaKey);

        return min(100, ($usage / $limit) * 100);
    }

    /**
     * Check quota thresholds and fire appropriate events.
     */
    protected function checkQuotaThresholds(string $quotaKey, QuotaUsage $quotaUsage): void
    {
        $limit = $this->getLimit($quotaKey);

        // If no limit is set, there's nothing to check
        if ($limit === null) {
            return;
        }

        $currentUsage = $quotaUsage->current_usage;
        $thresholds = config('billing.quota_warning_thresholds', [80, 90]);

        // Check if quota is exceeded
        if ($currentUsage > $limit) {
            $quotaUsage->update(['last_exceeded_at' => now()]);

            event(new QuotaExceeded(
                billable: $this,
                quotaKey: $quotaKey,
                currentUsage: $currentUsage,
                limit: $limit,
                overage: $currentUsage - $limit
            ));

            return;
        }

        // Check warning thresholds
        $percentage = ($currentUsage / $limit) * 100;

        foreach ($thresholds as $threshold) {
            if ($percentage >= $threshold && !$quotaUsage->hasTriggeredWarning($threshold)) {
                $quotaUsage->markWarningTriggered($threshold);

                event(new QuotaWarning(
                    billable: $this,
                    quotaKey: $quotaKey,
                    currentUsage: $currentUsage,
                    limit: $limit,
                    thresholdPercentage: $threshold
                ));
            }
        }
    }
}
