<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\CustomerCreditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class CustomerCredit extends Model
{
    use HasFactory;

    protected $table = 'billing_customer_credits';

    public const TYPE_REFUND = 'refund';
    public const TYPE_PROMOTIONAL = 'promotional';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';
    public const TYPE_INVOICE_PAYMENT = 'invoice_payment';
    public const TYPE_OTHER = 'other';

    protected $fillable = [
        'customer_id',
        'type',
        'amount',
        'currency',
        'balance_before',
        'balance_after',
        'description',
        'notes',
        'metadata',
        'invoice_id',
        'refund_id',
        'reference_type',
        'reference_id',
        'expires_at',
        'is_expired',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'expires_at' => 'datetime',
        'is_expired' => 'boolean',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'type' => self::TYPE_MANUAL_ADJUSTMENT,
        'balance_before' => 0,
        'balance_after' => 0,
        'is_expired' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $credit) {
            if (empty($credit->uuid)) {
                $credit->uuid = (string) Str::uuid();
            }

            if (empty($credit->currency)) {
                $credit->currency = config('billing.currency', 'usd');
            }
        });

        // Update customer balance after creating credit
        static::created(function (self $credit) {
            $credit->customer->updateCreditBalance();
        });
    }

    protected static function newFactory(): CustomerCreditFactory
    {
        return CustomerCreditFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    /**
     * Check if the credit is active (not expired)
     */
    public function isActive(): bool
    {
        if ($this->is_expired) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            $this->markAsExpired();
            return false;
        }

        return true;
    }

    /**
     * Mark credit as expired
     */
    public function markAsExpired(): void
    {
        $this->update(['is_expired' => true]);
        $this->customer->updateCreditBalance();
    }

    /**
     * Check if this is a credit (positive amount)
     */
    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Check if this is a debit (negative amount)
     */
    public function isDebit(): bool
    {
        return $this->amount < 0;
    }

    /**
     * Scope to active (non-expired) credits
     */
    public function scopeActive($query)
    {
        return $query->where('is_expired', false)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope to expired credits
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('is_expired', true)
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now());
                });
        });
    }

    /**
     * Scope to credits only (positive amounts)
     */
    public function scopeCredits($query)
    {
        return $query->where('amount', '>', 0);
    }

    /**
     * Scope to debits only (negative amounts)
     */
    public function scopeDebits($query)
    {
        return $query->where('amount', '<', 0);
    }

    /**
     * Scope by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
