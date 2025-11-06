<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Discount extends Model
{
    use HasFactory;

    protected $table = 'billing_discounts';

    protected $fillable = [
        'stripe_coupon_id',
        'stripe_promotion_code_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'currency',
        'applies_to',
        'applicable_plan_ids',
        'duration',
        'duration_in_months',
        'max_redemptions',
        'redemptions_count',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'applicable_plan_ids' => 'array',
        'max_redemptions' => 'integer',
        'redemptions_count' => 'integer',
        'duration_in_months' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'type' => 'percentage',
        'applies_to' => 'all',
        'duration' => 'once',
        'redemptions_count' => 0,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $discount) {
            if (empty($discount->uuid)) {
                $discount->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): DiscountFactory
    {
        return DiscountFactory::new();
    }

    public function appliedDiscounts(): HasMany
    {
        return $this->hasMany(AppliedDiscount::class);
    }

    /**
     * Check if the discount is currently valid
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        // Check start date
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        // Check expiration
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check max redemptions
        if ($this->max_redemptions && $this->redemptions_count >= $this->max_redemptions) {
            return false;
        }

        return true;
    }

    /**
     * Check if discount can be applied to a plan
     */
    public function canApplyToPlan(Plan $plan): bool
    {
        if ($this->applies_to === 'all') {
            return true;
        }

        if ($this->applies_to === 'specific_plans' && $this->applicable_plan_ids) {
            return in_array($plan->uuid, $this->applicable_plan_ids);
        }

        return false;
    }

    /**
     * Calculate the discount amount for a given price
     */
    public function calculateDiscount(float $amount, ?string $currency = null): float
    {
        if ($this->type === 'percentage') {
            return round($amount * ($this->value / 100), 2);
        }

        // Fixed discount
        if ($this->currency && $currency && $this->currency !== $currency) {
            // Currency mismatch - can't apply
            return 0;
        }

        return min($this->value, $amount); // Don't discount more than the amount
    }

    /**
     * Get the discounted price for a given amount
     */
    public function getDiscountedPrice(float $amount, ?string $currency = null): float
    {
        $discount = $this->calculateDiscount($amount, $currency);

        return max(0, $amount - $discount);
    }

    /**
     * Increment the redemption count
     */
    public function incrementRedemptions(): void
    {
        $this->increment('redemptions_count');
    }

    /**
     * Scope to find by code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope to active discounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_redemptions')
                    ->orWhereRaw('redemptions_count < max_redemptions');
            });
    }

    /**
     * Scope to code-based discounts (excludes admin-only discounts)
     */
    public function scopeCodeBased($query)
    {
        return $query->whereNotNull('code');
    }

    /**
     * Scope to admin-only discounts (no code)
     */
    public function scopeAdminOnly($query)
    {
        return $query->whereNull('code');
    }

    /**
     * Check if the discount has a Stripe coupon ID
     */
    public function hasStripeCouponId(): bool
    {
        return ! empty($this->stripe_coupon_id);
    }

    /**
     * Check if the discount has a Stripe promotion code ID
     */
    public function hasStripePromotionCodeId(): bool
    {
        return ! empty($this->stripe_promotion_code_id);
    }
}
