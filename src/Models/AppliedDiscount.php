<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\AppliedDiscountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AppliedDiscount extends Model
{
    use HasFactory;

    protected $table = 'billing_applied_discounts';

    protected $fillable = [
        'subscription_id',
        'discount_id',
        'applied_at',
        'expires_at',
        'total_uses',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'expires_at' => 'datetime',
        'total_uses' => 'integer',
    ];

    protected $attributes = [
        'total_uses' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $appliedDiscount) {
            if (empty($appliedDiscount->uuid)) {
                $appliedDiscount->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): AppliedDiscountFactory
    {
        return AppliedDiscountFactory::new();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Check if the applied discount is currently active
     */
    public function isActive(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->discount->isValid();
    }

    /**
     * Increment the usage count
     */
    public function incrementUses(): void
    {
        $this->increment('total_uses');
    }

    /**
     * Check if discount should expire after this use
     */
    public function shouldExpireAfterUse(): bool
    {
        $discount = $this->discount;

        if ($discount->duration === 'once') {
            return true;
        }

        if ($discount->duration === 'repeating' && $discount->duration_in_months) {
            return $this->total_uses >= $discount->duration_in_months;
        }

        return false;
    }

    /**
     * Scope to active applied discounts
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
