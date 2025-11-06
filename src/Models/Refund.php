<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Refund extends Model
{
    use HasFactory;

    protected $table = 'billing_refunds';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';

    public const REASON_DUPLICATE = 'duplicate';
    public const REASON_FRAUDULENT = 'fraudulent';
    public const REASON_REQUESTED_BY_CUSTOMER = 'requested_by_customer';
    public const REASON_OTHER = 'other';

    protected $fillable = [
        'customer_id',
        'invoice_id',
        'stripe_refund_id',
        'amount',
        'currency',
        'status',
        'reason',
        'description',
        'notes',
        'metadata',
        'processed_at',
        'failure_reason',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $refund) {
            if (empty($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
            }

            if (empty($refund->currency)) {
                $refund->currency = config('billing.currency', 'usd');
            }
        });

        static::created(function (self $refund) {
            event(new \Bhhaskin\Billing\Events\RefundCreated($refund));
        });
    }

    protected static function newFactory(): RefundFactory
    {
        return RefundFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function credit(): HasOne
    {
        return $this->hasOne(CustomerCredit::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo('created_by');
    }

    /**
     * Mark refund as succeeded
     */
    public function markAsSucceeded(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $this->update([
                'status' => self::STATUS_SUCCEEDED,
                'processed_at' => now(),
            ]);

            // Create credit for customer if configured
            if (config('billing.refunds.create_credit', true)) {
                $this->createCustomerCredit();
            }

            event(new \Bhhaskin\Billing\Events\RefundSucceeded($this));
        });
    }

    /**
     * Mark refund as failed
     */
    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'processed_at' => now(),
            'failure_reason' => $reason,
        ]);

        event(new \Bhhaskin\Billing\Events\RefundFailed($this, $reason));
    }

    /**
     * Cancel the refund
     */
    public function cancel(): void
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw new \RuntimeException('Can only cancel pending refunds');
        }

        $this->update([
            'status' => self::STATUS_CANCELED,
            'processed_at' => now(),
        ]);
    }

    /**
     * Create customer credit from refund
     */
    protected function createCustomerCredit(): void
    {
        $this->customer->credits()->create([
            'type' => CustomerCredit::TYPE_REFUND,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => 'Refund: ' . ($this->description ?? 'Invoice refund'),
            'invoice_id' => $this->invoice_id,
            'refund_id' => $this->id,
            'metadata' => [
                'refund_uuid' => $this->uuid,
                'reason' => $this->reason,
            ],
        ]);
    }

    /**
     * Check if refund is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if refund succeeded
     */
    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
    }

    /**
     * Check if refund failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if refund was canceled
     */
    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    /**
     * Check if refund has Stripe ID
     */
    public function hasStripeId(): bool
    {
        return ! empty($this->stripe_refund_id);
    }

    /**
     * Scope to pending refunds
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to succeeded refunds
     */
    public function scopeSucceeded($query)
    {
        return $query->where('status', self::STATUS_SUCCEEDED);
    }

    /**
     * Scope to failed refunds
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
