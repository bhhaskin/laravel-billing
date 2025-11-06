# Security

This document outlines the security features and best practices implemented in the Laravel Billing package.

## Security Features

### 1. Authorization Policies

The package implements comprehensive authorization policies for all sensitive operations:

#### RefundPolicy (`src/Policies/RefundPolicy.php`)
- `viewAny()` - View all refunds (customer must exist)
- `view()` - View specific refund (must belong to customer)
- `create()` - Create refund (invoice must be paid and not fully refunded)
- `cancel()` - Cancel pending refund (must belong to customer and be pending)

#### CreditPolicy (`src/Policies/CreditPolicy.php`)
- `viewAny()` - View all credits (customer must exist)
- `view()` - View specific credit (must belong to customer)
- `viewBalance()` - View credit balance (customer must exist)
- `viewSummary()` - View credit summary (customer must exist)

#### SubscriptionPolicy (`src/Policies/SubscriptionPolicy.php`)
- `changePlan()` - Change subscription plan (must own subscription, be active/trialing, and plan must be different)
- `previewPlanChange()` - Preview plan change costs (same as changePlan)
- `cancelScheduledPlanChange()` - Cancel scheduled change (must own subscription with scheduled change)
- `applyDiscount()` - Apply discount code (must own subscription and be active/trialing)

#### InvoicePolicy (`src/Policies/InvoicePolicy.php`)
- `viewAny()` - View all invoices (customer must exist)
- `view()` - View specific invoice (must belong to customer)
- `refund()` - Refund invoice (uses RefundPolicy create check)

**Usage in Controllers:**
```php
$this->authorize('view', $refund);
$this->authorize('create', [Refund::class, $invoice]);
$this->authorize('changePlan', [$subscription, $newPlan]);
```

### 2. Form Request Validation

#### CreateRefundRequest (`src/Http/Requests/CreateRefundRequest.php`)
- Validates refund amount, reason, and description
- **Idempotency Check**: Prevents duplicate refund requests within 5-minute window
- Checks invoice is paid and not fully refunded
- Validates amount doesn't exceed remaining refundable amount
- Sanitizes description input (strips HTML tags)

**Features:**
- Amount validation: min 0.01, max 999999.99, 2 decimal places
- Reason validation: enum values (duplicate, fraudulent, requested_by_customer, other)
- Description: max 500 characters, HTML stripped
- Duplicate detection: Same amount + pending status + created within 5 minutes

#### ChangePlanRequest (`src/Http/Requests/ChangePlanRequest.php`)
- Validates new plan UUID exists
- Validates schedule flag and schedule_for date
- Validates proration flag
- Validates quantity (1-100)
- Checks new plan is active and different from current plan
- Validates subscription is active or trialing

#### PreviewPlanChangeRequest (`src/Http/Requests/PreviewPlanChangeRequest.php`)
- Validates new plan UUID exists
- Simple validation for preview operations

**Usage in Controllers:**
```php
public function store(CreateRefundRequest $request): JsonResponse
{
    $validated = $request->validated();
    // Validation and authorization already handled
}
```

### 3. API Resources (Data Sanitization)

All API responses use Resource classes to prevent sensitive data leakage:

#### Excluded Sensitive Fields

**RefundResource:**
- ❌ Internal database IDs
- ❌ Stripe refund IDs
- ❌ created_by polymorphic IDs
- ❌ failure_reason (internal)
- ❌ notes (internal)
- ❌ metadata (internal)

**CustomerCreditResource:**
- ❌ Internal database IDs
- ❌ reference polymorphic IDs
- ❌ created_by polymorphic IDs
- ❌ notes (internal)
- ❌ metadata (internal)

**SubscriptionResource:**
- ❌ Internal database IDs
- ❌ Stripe subscription ID
- ❌ Stripe status
- ❌ metadata (internal)
- ❌ failed_payment_count

**InvoiceResource:**
- ❌ Internal database IDs
- ❌ Stripe invoice ID
- ❌ payment_method_id
- ❌ metadata (internal)

**PlanResource:**
- ❌ Internal database IDs
- ❌ Stripe product/price IDs
- ❌ metadata (internal)
- ❌ deleted_at timestamp

**Usage:**
```php
return RefundResource::collection($refunds)->response();
return (new SubscriptionResource($subscription))->response();
```

### 4. Database Transactions

All financial operations are wrapped in database transactions for ACID compliance:

**Protected Operations:**
- `Customer::addCredit()` - Creates credit + updates balance
- `Customer::applyCreditsToInvoice()` - Deducts credit + creates invoice item + recalculates
- `Invoice::addDiscountItems()` - Creates multiple discount line items
- `Subscription::changePlan()` - Updates subscription + items + handles proration
- `Subscription::applyScheduledPlanChange()` - Clears schedule + applies change
- `Refund::markAsSucceeded()` - Updates status + creates customer credit
- `Billable::subscribe()` - Creates subscription + adds items

**Example:**
```php
public function addCredit(...): CustomerCredit
{
    return DB::transaction(function () use (...) {
        $credit = $this->credits()->create([...]);
        $this->updateCreditBalance();
        event(new CreditAdded($credit));
        return $credit;
    });
}
```

### 5. Mass Assignment Protection

All methods that accept `$options` arrays now whitelist allowed fields:

**Customer::addCredit()** - Allowed fields:
- `invoice_id`, `refund_id`
- `reference_type`, `reference_id`
- `notes`, `metadata`
- `expires_at`
- `created_by_type`, `created_by_id`

**Customer::createRefund()** - Allowed fields:
- `notes`, `metadata`
- `created_by_type`, `created_by_id`

**Billable::getOrCreateCustomer()** - Allowed fields:
- `workspace_id`
- `stripe_id`
- `metadata`

**Example:**
```php
$allowedOptions = Arr::only($options, [
    'invoice_id',
    'refund_id',
    'notes',
    'metadata',
]);
$credit = $this->credits()->create(array_merge([...], $allowedOptions));
```

### 6. Rate Limiting

All API routes have rate limiting applied:

| Route Type | Limit | Examples |
|------------|-------|----------|
| Public routes | 60 req/min | List plans |
| Read operations | 60 req/min | View subscriptions, invoices, credits, refunds |
| Write operations | 30 req/min | Create/cancel subscriptions, apply/remove discounts |
| Financial operations | 10 req/min | Change plan, create refund, cancel refund |
| Webhooks | No limit | Stripe webhooks (protected by signature verification) |

**Implementation:**
```php
Route::middleware('throttle:60,1')->group(function () {
    // Read routes
});

Route::middleware('throttle:10,1')->group(function () {
    // Financial operations
});
```

### 7. Audit Logging

The package uses `BillingAudit` for tracking financial operations (if `bhhaskin/laravel-audit` is installed):

**Logged Events:**
- Subscription changes (created, canceled, resumed, plan changed)
- Invoice actions (refunded, paid, voided)
- Payment actions (succeeded, failed)
- Credit operations (added, applied, refunded)

**Example:**
```php
BillingAudit::recordInvoiceAction($invoice, 'refunded', [
    'refund_uuid' => $refund->uuid,
    'amount' => $refund->amount,
]);
```

## Security Best Practices

### Input Validation
- All user input is validated using Form Requests
- HTML is stripped from description fields
- Amounts are validated with min/max and decimal precision
- UUIDs are validated against database

### Output Sanitization
- API Resources prevent sensitive data exposure
- Only public-safe fields are returned
- Stripe IDs and internal metadata are excluded

### Authorization
- All sensitive operations require authorization
- Policies check ownership and state requirements
- Authorization happens before business logic

### CSRF Protection
- Consumer apps should apply CSRF middleware
- Package routes expect `auth:sanctum` middleware from consumer

### Rate Limiting
- Financial operations limited to 10 req/min
- Prevents abuse and brute force attempts
- Consumer apps can customize limits

### Idempotency
- Refund creation prevents duplicates within 5 minutes
- Based on invoice + amount + pending status + timestamp

## Recommendations for Consumer Applications

### 1. Authentication
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    require __DIR__.'/../vendor/bhhaskin/laravel-billing/routes/api.php';
});
```

### 2. Additional Authorization
Consumer apps can extend or override policies:
```php
Gate::define('billing.refund.create', function ($user, $invoice) {
    // Additional custom authorization logic
});
```

### 3. Environment Variables
```env
BILLING_STRIPE_KEY=pk_live_xxx
BILLING_STRIPE_SECRET=sk_live_xxx
BILLING_STRIPE_WEBHOOK_SECRET=whsec_xxx
```

### 4. HTTPS Only
Always use HTTPS in production for billing operations.

### 5. Monitoring
- Monitor failed payment attempts
- Track refund patterns
- Alert on unusual activity
- Review audit logs regularly

## Reporting Security Issues

If you discover a security vulnerability, please email security@example.com. Do not create public GitHub issues for security problems.

## Security Updates

This package follows semantic versioning. Security updates will be released as patch versions and documented in CHANGELOG.md.
