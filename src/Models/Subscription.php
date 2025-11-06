<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\SubscriptionFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $customer_id
 * @property int|null $workspace_id
 * @property string|null $stripe_id
 * @property string|null $stripe_status
 * @property string $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $ends_at
 * @property int $failed_payment_count
 * @property Carbon|null $last_failed_payment_at
 * @property array|null $metadata
 */
class Subscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_INCOMPLETE = 'incomplete';

    protected $fillable = [
        'uuid',
        'customer_id',
        'workspace_id',
        'previous_plan_id',
        'scheduled_plan_id',
        'stripe_id',
        'stripe_status',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'canceled_at',
        'ends_at',
        'plan_changed_at',
        'plan_change_scheduled_for',
        'failed_payment_count',
        'last_failed_payment_at',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'customer_id',
        'stripe_id',
        'stripe_status',
    ];

    protected $hidden = [
        'stripe_id',
        'stripe_status',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'canceled_at' => 'datetime',
        'ends_at' => 'datetime',
        'plan_changed_at' => 'datetime',
        'plan_change_scheduled_for' => 'datetime',
        'last_failed_payment_at' => 'datetime',
        'failed_payment_count' => 'integer',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'failed_payment_count' => 0,
    ];

    public function getTable(): string
    {
        return config('billing.tables.subscriptions', 'billing_subscriptions');
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription) {
            if (empty($subscription->uuid)) {
                $subscription->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): SubscriptionFactory
    {
        return SubscriptionFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workspace(): BelongsTo
    {
        if (! config('billing.workspace_model')) {
            throw new \RuntimeException('Workspace model is not configured');
        }

        return $this->belongsTo(config('billing.workspace_model'));
    }

    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'previous_plan_id');
    }

    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function appliedDiscounts(): HasMany
    {
        return $this->hasMany(AppliedDiscount::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeTrialing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRIALING);
    }

    public function scopePastDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAST_DUE);
    }

    public function scopeCanceled(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANCELED);
    }

    public function scopeEnded(Builder $query): Builder
    {
        return $query->whereNotNull('ends_at')->where('ends_at', '<=', now());
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->hasEnded();
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }

    public function onGracePeriod(): bool
    {
        return $this->canceled_at && ! $this->hasEnded();
    }

    public function hasStripeId(): bool
    {
        return ! empty($this->stripe_id);
    }

    public function addItem(Plan $plan, int $quantity = 1): SubscriptionItem
    {
        // Validate that addon doesn't require a plan if this subscription has no plans
        if ($plan->requiresPlan() && ! $this->hasPlans()) {
            throw new \InvalidArgumentException('This addon requires a base plan');
        }

        return $this->items()->create([
            'plan_id' => $plan->id,
            'quantity' => $quantity,
        ]);
    }

    public function hasPlans(): bool
    {
        return $this->items()->whereHas('plan', function (Builder $query) {
            $query->where('type', Plan::TYPE_PLAN);
        })->exists();
    }

    public function hasPlan(Plan $plan): bool
    {
        return $this->items()->where('plan_id', $plan->id)->exists();
    }

    public function getItem(Plan $plan): ?SubscriptionItem
    {
        return $this->items()->where('plan_id', $plan->id)->first();
    }

    /**
     * Apply a discount to this subscription
     */
    public function applyDiscount(Discount $discount): AppliedDiscount
    {
        if (! $discount->isValid()) {
            throw new \InvalidArgumentException('Discount is not valid');
        }

        // Check if discount can apply to any of the subscription's plans
        $hasApplicablePlan = $this->items->contains(function (SubscriptionItem $item) use ($discount) {
            return $discount->canApplyToPlan($item->plan);
        });

        if (! $hasApplicablePlan) {
            throw new \InvalidArgumentException('Discount cannot be applied to any plans in this subscription');
        }

        // Check if discount is already applied
        if ($this->hasDiscount($discount)) {
            throw new \InvalidArgumentException('Discount is already applied to this subscription');
        }

        // Calculate expiration date if applicable
        $expiresAt = null;
        if ($discount->duration === 'once') {
            $expiresAt = $this->current_period_end;
        } elseif ($discount->duration === 'repeating' && $discount->duration_in_months) {
            $expiresAt = now()->addMonths($discount->duration_in_months);
        }

        // Increment discount usage
        $discount->incrementRedemptions();

        // Create the applied discount
        return $this->appliedDiscounts()->create([
            'discount_id' => $discount->id,
            'applied_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Check if a discount is applied to this subscription
     */
    public function hasDiscount(Discount $discount): bool
    {
        return $this->appliedDiscounts()
            ->where('discount_id', $discount->id)
            ->active()
            ->exists();
    }

    /**
     * Get all active discounts for this subscription
     */
    public function getActiveDiscounts()
    {
        return $this->appliedDiscounts()
            ->active()
            ->with('discount')
            ->get()
            ->filter(fn (AppliedDiscount $applied) => $applied->isActive())
            ->map(fn (AppliedDiscount $applied) => $applied->discount);
    }

    /**
     * Remove a discount from this subscription
     */
    public function removeDiscount(Discount $discount): bool
    {
        return $this->appliedDiscounts()
            ->where('discount_id', $discount->id)
            ->delete();
    }

    /**
     * Calculate the total discount amount for a given price
     */
    public function calculateDiscountAmount(float $amount, ?string $currency = null): float
    {
        $totalDiscount = 0;

        foreach ($this->getActiveDiscounts() as $discount) {
            $discountAmount = $discount->calculateDiscount($amount - $totalDiscount, $currency);
            $totalDiscount += $discountAmount;

            // Don't discount more than the total amount
            if ($totalDiscount >= $amount) {
                return $amount;
            }
        }

        return $totalDiscount;
    }

    /**
     * Change subscription to a new plan
     */
    public function changePlan(Plan $newPlan, array $options = []): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($newPlan, $options) {
            // Get current plan from first item
            $currentPlanItem = $this->items()->whereHas('plan', function ($query) {
                $query->where('type', Plan::TYPE_PLAN);
            })->first();

            if (! $currentPlanItem) {
                throw new \RuntimeException('No plan found in subscription');
            }

            $currentPlan = $currentPlanItem->plan;

            if ($currentPlan->id === $newPlan->id) {
                throw new \InvalidArgumentException('New plan is the same as current plan');
            }

            // Check if change should be scheduled
            if ($options['schedule'] ?? false) {
                return $this->schedulePlanChange($newPlan, $options['schedule_for'] ?? $this->current_period_end);
            }

            // Determine if proration should be applied
            $prorate = $options['prorate'] ?? $currentPlan->prorate_changes;

            // Store previous plan
            $this->update([
                'previous_plan_id' => $currentPlan->id,
                'plan_changed_at' => now(),
            ]);

            // Update the plan item
            $currentPlanItem->update([
                'plan_id' => $newPlan->id,
                'quantity' => $options['quantity'] ?? $currentPlanItem->quantity,
            ]);

            // Handle proration if enabled
            if ($prorate && $this->hasStripeId()) {
                $this->handlePlanChangeProration($currentPlan, $newPlan);
            }

            event(new \Bhhaskin\Billing\Events\PlanChanged($this, $currentPlan, $newPlan, $prorate));

            return $this->fresh();
        });
    }

    /**
     * Schedule a plan change for the end of the current period
     */
    public function schedulePlanChange(Plan $newPlan, ?\DateTimeInterface $scheduleFor = null): self
    {
        $currentPlan = $this->getCurrentPlan();
        $scheduledFor = $scheduleFor ?? $this->current_period_end;

        $this->update([
            'scheduled_plan_id' => $newPlan->id,
            'plan_change_scheduled_for' => $scheduledFor,
        ]);

        event(new \Bhhaskin\Billing\Events\PlanChangeScheduled($this, $currentPlan, $newPlan, $scheduledFor));

        return $this;
    }

    /**
     * Cancel scheduled plan change
     */
    public function cancelScheduledPlanChange(): self
    {
        $this->update([
            'scheduled_plan_id' => null,
            'plan_change_scheduled_for' => null,
        ]);

        return $this;
    }

    /**
     * Check if subscription has a scheduled plan change
     */
    public function hasScheduledPlanChange(): bool
    {
        return $this->scheduled_plan_id !== null;
    }

    /**
     * Apply scheduled plan change if time has come
     */
    public function applyScheduledPlanChange(): bool
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            if (! $this->hasScheduledPlanChange()) {
                return false;
            }

            if ($this->plan_change_scheduled_for && $this->plan_change_scheduled_for->isFuture()) {
                return false;
            }

            $newPlan = Plan::find($this->scheduled_plan_id);

            if (! $newPlan) {
                $this->cancelScheduledPlanChange();
                return false;
            }

            // Clear the scheduled change
            $this->update([
                'scheduled_plan_id' => null,
                'plan_change_scheduled_for' => null,
            ]);

            // Apply the change
            $this->changePlan($newPlan, ['prorate' => false]);

            return true;
        });
    }

    /**
     * Preview plan change costs
     */
    public function previewPlanChange(Plan $newPlan): array
    {
        $currentPlanItem = $this->items()->whereHas('plan', function ($query) {
            $query->where('type', Plan::TYPE_PLAN);
        })->first();

        if (! $currentPlanItem) {
            throw new \RuntimeException('No plan found in subscription');
        }

        $currentPlan = $currentPlanItem->plan;

        // Calculate remaining time in current period
        $now = now();
        $periodStart = $this->current_period_start;
        $periodEnd = $this->current_period_end;

        $totalPeriodSeconds = abs($periodEnd->diffInSeconds($periodStart));
        $remainingSeconds = abs($periodEnd->diffInSeconds($now));
        $usedSeconds = abs($now->diffInSeconds($periodStart));

        $remainingRatio = $totalPeriodSeconds > 0 ? $remainingSeconds / $totalPeriodSeconds : 0;
        $usedRatio = $totalPeriodSeconds > 0 ? $usedSeconds / $totalPeriodSeconds : 0;

        // Calculate proration
        $currentPlanCost = (float) $currentPlan->price;
        $newPlanCost = (float) $newPlan->price;

        $unusedCredit = $currentPlanCost * $remainingRatio;
        $newPlanProrated = $newPlanCost * $remainingRatio;

        $amountDue = $newPlanProrated - $unusedCredit;

        return [
            'current_plan' => [
                'id' => $currentPlan->id,
                'name' => $currentPlan->name,
                'price' => $currentPlanCost,
                'unused_amount' => round($unusedCredit, 2),
            ],
            'new_plan' => [
                'id' => $newPlan->id,
                'name' => $newPlan->name,
                'price' => $newPlanCost,
                'prorated_amount' => round($newPlanProrated, 2),
            ],
            'proration' => [
                'unused_amount' => round($unusedCredit, 2),
                'charge_for_new' => round($newPlanProrated, 2),
                'amount_due' => round($amountDue, 2),
                'is_upgrade' => $newPlanCost > $currentPlanCost,
                'is_downgrade' => $newPlanCost < $currentPlanCost,
            ],
            'period' => [
                'start' => $periodStart->toIso8601String(),
                'end' => $periodEnd->toIso8601String(),
                'remaining_days' => $periodEnd->diffInDays($now),
            ],
        ];
    }

    /**
     * Handle proration for plan change
     */
    protected function handlePlanChangeProration(Plan $oldPlan, Plan $newPlan): void
    {
        // This would typically create a proration invoice item
        // For now, we'll rely on Stripe's automatic proration
        // You can extend this to create manual proration records
    }

    /**
     * Get the current primary plan
     */
    public function getCurrentPlan(): ?Plan
    {
        $planItem = $this->items()->whereHas('plan', function ($query) {
            $query->where('type', Plan::TYPE_PLAN);
        })->first();

        return $planItem?->plan;
    }

    /**
     * Check if this is an upgrade
     */
    public function isUpgrade(Plan $newPlan): bool
    {
        $currentPlan = $this->getCurrentPlan();

        if (! $currentPlan) {
            return false;
        }

        return $newPlan->price > $currentPlan->price;
    }

    /**
     * Check if this is a downgrade
     */
    public function isDowngrade(Plan $newPlan): bool
    {
        $currentPlan = $this->getCurrentPlan();

        if (! $currentPlan) {
            return false;
        }

        return $newPlan->price < $currentPlan->price;
    }
}
