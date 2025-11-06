<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $billable_type
 * @property int $billable_id
 * @property int|null $workspace_id
 * @property string|null $stripe_id
 * @property string $email
 * @property string|null $name
 * @property array|null $metadata
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'billable_type',
        'billable_id',
        'workspace_id',
        'stripe_id',
        'credit_balance',
        'email',
        'name',
        'metadata',
    ];

    protected $guarded = [
        'id',
        'stripe_id',
    ];

    protected $hidden = [
        'stripe_id',
    ];

    protected $casts = [
        'credit_balance' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('billing.tables.customers', 'billing_customers');
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer) {
            if (empty($customer->uuid)) {
                $customer->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function workspace(): BelongsTo
    {
        if (! config('billing.workspace_model')) {
            throw new \RuntimeException('Workspace model is not configured');
        }

        return $this->belongsTo(config('billing.workspace_model'));
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function defaultPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethods()->where('is_default', true)->first();
    }

    public function hasStripeId(): bool
    {
        return ! empty($this->stripe_id);
    }

    public function activeSubscriptions(): HasMany
    {
        return $this->subscriptions()->whereIn('status', ['active', 'trialing']);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscriptions()->exists();
    }

    /**
     * Update customer credit balance based on all credits
     */
    public function updateCreditBalance(): void
    {
        $balance = $this->credits()
            ->active()
            ->sum('amount');

        $this->update(['credit_balance' => $balance]);
    }

    /**
     * Get available credit balance
     */
    public function getAvailableCredit(): float
    {
        return (float) $this->credit_balance;
    }

    /**
     * Add credit to customer balance
     */
    public function addCredit(
        float $amount,
        string $type = CustomerCredit::TYPE_MANUAL_ADJUSTMENT,
        ?string $description = null,
        array $options = []
    ): CustomerCredit {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($amount, $type, $description, $options) {
            $balanceBefore = $this->getAvailableCredit();

            // Whitelist allowed optional fields to prevent mass assignment vulnerabilities
            $allowedOptions = \Illuminate\Support\Arr::only($options, [
                'invoice_id',
                'refund_id',
                'reference_type',
                'reference_id',
                'notes',
                'metadata',
                'expires_at',
                'created_by_type',
                'created_by_id',
            ]);

            $credit = $this->credits()->create(array_merge([
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceBefore + $amount,
            ], $allowedOptions));

            // Update the customer's credit balance
            $this->updateCreditBalance();

            event(new \Bhhaskin\Billing\Events\CreditAdded($credit));

            return $credit;
        });
    }

    /**
     * Deduct credit from customer balance
     */
    public function deductCredit(
        float $amount,
        string $type = CustomerCredit::TYPE_INVOICE_PAYMENT,
        ?string $description = null,
        array $options = []
    ): CustomerCredit {
        return $this->addCredit(-$amount, $type, $description, $options);
    }

    /**
     * Apply credits to an invoice
     */
    public function applyCreditsToInvoice(Invoice $invoice): float
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($invoice) {
            $availableCredit = $this->getAvailableCredit();

            if ($availableCredit <= 0 || $invoice->total <= 0) {
                return 0;
            }

            $amountToApply = min($availableCredit, $invoice->total);

            // Deduct credit
            $credit = $this->deductCredit(
                $amountToApply,
                CustomerCredit::TYPE_INVOICE_PAYMENT,
                "Applied to invoice {$invoice->invoice_number}",
                ['invoice_id' => $invoice->id]
            );

            // Add credit line item to invoice
            $invoice->items()->create([
                'description' => 'Account Credit',
                'quantity' => 1,
                'unit_price' => -$amountToApply,
                'amount' => -$amountToApply,
                'is_discount' => true,
            ]);

            $invoice->calculateTotals();

            event(new \Bhhaskin\Billing\Events\CreditApplied($credit, $invoice, $amountToApply));

            return $amountToApply;
        });
    }

    /**
     * Create a refund for a customer
     */
    public function createRefund(
        float $amount,
        ?Invoice $invoice = null,
        string $reason = Refund::REASON_REQUESTED_BY_CUSTOMER,
        ?string $description = null,
        array $options = []
    ): Refund {
        // Whitelist allowed optional fields to prevent mass assignment vulnerabilities
        $allowedOptions = \Illuminate\Support\Arr::only($options, [
            'notes',
            'metadata',
            'created_by_type',
            'created_by_id',
        ]);

        return $this->refunds()->create(array_merge([
            'invoice_id' => $invoice?->id,
            'amount' => $amount,
            'reason' => $reason,
            'description' => $description,
        ], $allowedOptions));
    }
}
