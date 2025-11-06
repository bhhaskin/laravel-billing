<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\InvoiceFactory;
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
 * @property string|null $stripe_id
 * @property string $invoice_number
 * @property string $status
 * @property float $subtotal
 * @property float $tax
 * @property float $total
 * @property string $currency
 * @property Carbon|null $due_date
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property string|null $notes
 * @property array|null $metadata
 */
class Invoice extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';
    public const STATUS_UNCOLLECTIBLE = 'uncollectible';

    protected $fillable = [
        'uuid',
        'customer_id',
        'stripe_id',
        'invoice_number',
        'status',
        'subtotal',
        'discount',
        'tax',
        'total',
        'currency',
        'due_date',
        'paid_at',
        'voided_at',
        'notes',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'customer_id',
        'stripe_id',
        'invoice_number',
        'paid_at',
        'voided_at',
    ];

    protected $hidden = [
        'stripe_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'subtotal' => 0,
        'discount' => 0,
        'tax' => 0,
        'total' => 0,
    ];

    public function getTable(): string
    {
        return config('billing.tables.invoices', 'billing_invoices');
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            if (empty($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid();
            }

            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }

            if (empty($invoice->currency)) {
                $invoice->currency = config('billing.currency', 'usd');
            }
        });
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date && $this->due_date->isPast();
    }

    public function calculateTotals(): void
    {
        // Calculate subtotal from non-discount items
        $this->subtotal = $this->items()->where('is_discount', false)->sum('amount');

        // Calculate total discount from discount items (should be negative amounts)
        $this->discount = abs($this->items()->where('is_discount', true)->sum('amount'));

        // Calculate total: subtotal - discount + tax
        $this->total = max(0, $this->subtotal - $this->discount + $this->tax);

        $this->save();
    }

    public function markAsPaid(): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    public function void(): void
    {
        $this->update([
            'status' => self::STATUS_VOID,
            'voided_at' => now(),
        ]);
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = config('billing.invoice.number_prefix', 'INV-');
        $startingNumber = config('billing.invoice.starting_number', 1000);

        // Use database locking to prevent race conditions
        return \DB::transaction(function () use ($prefix, $startingNumber) {
            // Lock the table for this transaction to prevent concurrent number generation
            $lastInvoice = static::orderBy('id', 'desc')->lockForUpdate()->first();

            if (! $lastInvoice) {
                return $prefix . $startingNumber;
            }

            // Extract number from last invoice
            $lastNumber = (int) str_replace($prefix, '', $lastInvoice->invoice_number);
            $nextNumber = max($lastNumber + 1, $startingNumber);

            return $prefix . $nextNumber;
        });
    }

    /**
     * Add discount line items for a subscription's active discounts
     */
    public function addDiscountItems(Subscription $subscription): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($subscription) {
            $activeDiscounts = $subscription->getActiveDiscounts();
            $currency = $this->currency;

            // Calculate the base amount to apply discounts to (subtotal before discounts)
            $baseAmount = $this->items()->where('is_discount', false)
                ->where('subscription_id', $subscription->id)
                ->sum('amount');

            if ($baseAmount <= 0 || $activeDiscounts->isEmpty()) {
                return;
            }

            $remainingAmount = $baseAmount;

            foreach ($activeDiscounts as $discount) {
                if ($remainingAmount <= 0) {
                    break;
                }

                $discountAmount = $discount->calculateDiscount($remainingAmount, $currency);

                if ($discountAmount > 0) {
                    // Add discount as a negative line item
                    $this->items()->create([
                        'subscription_id' => $subscription->id,
                        'discount_id' => $discount->id,
                        'description' => $discount->name . ' (' . $this->formatDiscountValue($discount) . ')',
                        'quantity' => 1,
                        'unit_price' => -$discountAmount,
                        'amount' => -$discountAmount,
                        'is_discount' => true,
                        'period_start' => $subscription->current_period_start,
                        'period_end' => $subscription->current_period_end,
                    ]);

                    $remainingAmount -= $discountAmount;
                }
            }
        });
    }

    /**
     * Format discount value for display
     */
    protected function formatDiscountValue(Discount $discount): string
    {
        if ($discount->type === 'percentage') {
            return $discount->value . '% off';
        }

        return strtoupper($discount->currency) . ' ' . number_format($discount->value, 2) . ' off';
    }

    /**
     * Create a refund for this invoice
     */
    public function refund(
        ?float $amount = null,
        string $reason = Refund::REASON_REQUESTED_BY_CUSTOMER,
        ?string $description = null
    ): Refund {
        // Default to full refund
        $refundAmount = $amount ?? $this->total;

        // Check if refund amount is valid
        $totalRefunded = $this->refunds()->succeeded()->sum('amount');
        $remainingRefundable = $this->total - $totalRefunded;

        if ($refundAmount > $remainingRefundable) {
            throw new \InvalidArgumentException(
                "Refund amount ({$refundAmount}) exceeds remaining refundable amount ({$remainingRefundable})"
            );
        }

        return $this->customer->createRefund(
            $refundAmount,
            $this,
            $reason,
            $description ?? "Refund for invoice {$this->invoice_number}"
        );
    }

    /**
     * Get total amount refunded for this invoice
     */
    public function getTotalRefunded(): float
    {
        return (float) $this->refunds()->succeeded()->sum('amount');
    }

    /**
     * Get remaining refundable amount
     */
    public function getRemainingRefundable(): float
    {
        return max(0, $this->total - $this->getTotalRefunded());
    }

    /**
     * Check if invoice has been fully refunded
     */
    public function isFullyRefunded(): bool
    {
        return $this->getTotalRefunded() >= $this->total;
    }

    /**
     * Check if invoice has been partially refunded
     */
    public function isPartiallyRefunded(): bool
    {
        $refunded = $this->getTotalRefunded();
        return $refunded > 0 && $refunded < $this->total;
    }
}
