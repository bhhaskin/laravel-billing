# Laravel Billing

Stripe-based subscription and billing management for Laravel applications.

## Features

### Subscription Management
- **Multiple Plans & Add-ons** - Support for base plans and both standalone and plan-dependent add-ons
- **Stripe Integration** - Full Stripe integration with webhook support
- **Proration** - Configurable proration for plan changes and cancellations
- **Plan Changes** - Immediate and scheduled plan changes with upgrade/downgrade detection
- **Trial Periods** - Optional trial periods per plan
- **Grace Periods** - Configurable grace periods for failed payments
- **Discount Codes** - Percentage and fixed discounts with flexible durations

### Financial Operations
- **Refund System** - Full and partial refunds with automatic credit creation
- **Customer Credits** - Complete credit/debit tracking with running balances and expiration support
- **Usage-Based Billing** - Track and bill for metered usage
- **Quota Tracking** - Track usage against limits with automatic warnings and exceeded events

### Developer Experience
- **Workspace Support** - Optional multi-tenancy with bhhaskin/laravel-workspaces
- **Audit Trail** - Optional audit logging with bhhaskin/laravel-audit
- **Fully Tested** - Comprehensive test suite with 90+ tests
- **UUIDs** - All models include UUIDs for secure API endpoints

## Installation

```bash
composer require bhhaskin/laravel-billing
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=billing-config
```

### Publish and Run Migrations

```bash
php artisan vendor:publish --tag=billing-migrations
php artisan migrate
```

### Configuration

Add your Stripe credentials to `.env`:

```env
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
STRIPE_WEBHOOK_SECRET=your_webhook_secret
```

## Usage

### Add Billable Trait to User Model

```php
use Bhhaskin\Billing\Concerns\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### Create Plans

```php
use Bhhaskin\Billing\Models\Plan;

$plan = Plan::create([
    'name' => 'Professional',
    'slug' => 'professional',
    'price' => 19.99,
    'interval' => 'monthly',
    'features' => ['feature1', 'feature2'],
    'limits' => ['websites' => 5, 'storage_gb' => 100],
]);
```

### Subscribe Users

```php
$user = User::find(1);
$plan = Plan::where('slug', 'professional')->first();

$subscription = $user->subscribe($plan);
```

### Check Subscriptions

```php
// Check if user has any active subscription
if ($user->hasActiveSubscription()) {
    // ...
}

// Check if user is subscribed to specific plan
if ($user->subscribedToPlan($plan)) {
    // ...
}

// Get combined limits from all subscriptions
$limits = $user->getCombinedLimits();
$websiteLimit = $user->getLimit('websites');

// Check if user has specific feature
if ($user->hasFeature('ssl_certificate')) {
    // ...
}
```

### Plan Changes

Change subscription plans immediately or schedule for later:

```php
$subscription = $user->subscriptions()->first();
$newPlan = Plan::where('slug', 'enterprise')->first();

// Immediate plan change with proration
$subscription->changePlan($newPlan, [
    'prorate' => true,
]);

// Schedule plan change for end of period
$subscription->changePlan($newPlan, [
    'schedule' => true,
    'schedule_for' => $subscription->current_period_end,
]);

// Preview plan change costs before applying
$preview = $subscription->previewPlanChange($newPlan);
// Returns: amount, proration_credit, upgrade_charge, is_upgrade, etc.

// Cancel scheduled plan change
$subscription->cancelScheduledPlanChange();
```

### Customer Credits

Manage customer credit balances for refunds, promotions, and adjustments:

```php
// Add credit to customer balance
$user->customer->addCredit(
    amount: 25.00,
    type: 'promotional',
    description: 'Holiday promotion credit',
    options: ['expires_at' => now()->addMonths(3)]
);

// Get available credit balance
$balance = $user->customer->getAvailableCredit();

// Credits are automatically applied to invoices
// Or manually apply credits to an invoice
$amountApplied = $user->customer->applyCreditsToInvoice($invoice);

// View credit history
$credits = $user->customer->credits()
    ->where('type', 'refund')
    ->get();
```

### Refunds

Create full or partial refunds for paid invoices:

```php
$invoice = $user->invoices()->where('status', 'paid')->first();

// Full refund
$refund = $invoice->refund();

// Partial refund
$refund = $invoice->refund(
    amount: 10.00,
    reason: 'requested_by_customer',
    description: 'Partial service credit'
);

// Check refund status
if ($refund->isSucceeded()) {
    // Refund completed - customer credit created
}

// Get refund information
$totalRefunded = $invoice->getTotalRefunded();
$remainingRefundable = $invoice->getRemainingRefundable();

if ($invoice->isFullyRefunded()) {
    // Invoice completely refunded
}
```

### Quota Tracking

Track usage against plan limits and automatically fire events when quotas are reached:

```php
// Record usage for a quota (e.g., disk space in MB)
$user->recordUsage('disk_space', 150.5);

// Get current usage
$currentUsage = $user->getUsage('disk_space');

// Set absolute usage (useful for syncing from external sources)
$user->setUsage('bandwidth', 5000);

// Decrement usage (e.g., when files are deleted)
$user->decrementUsage('disk_space', 50);

// Check if over quota
if ($user->isOverQuota('disk_space')) {
    // Prevent new uploads
}

// Get remaining quota
$remaining = $user->getRemainingQuota('websites'); // Returns remaining count

// Get percentage used
$percentage = $user->getQuotaPercentage('disk_space'); // Returns 0-100

// Reset usage to zero
$user->resetUsage('disk_space');
```

#### Quota Events

The package fires events when quotas reach warning thresholds (default: 80%, 90%) or are exceeded:

```php
// Listen to quota events in your EventServiceProvider
use Bhhaskin\Billing\Events\QuotaWarning;
use Bhhaskin\Billing\Events\QuotaExceeded;

protected $listen = [
    QuotaWarning::class => [
        SendQuotaWarningNotification::class,
    ],
    QuotaExceeded::class => [
        ArchiveUserFiles::class,
        SendQuotaExceededNotification::class,
    ],
];
```

Event properties:
- `$event->billable` - The billable model (User)
- `$event->quotaKey` - The quota identifier (e.g., 'disk_space')
- `$event->currentUsage` - Current usage amount
- `$event->limit` - The quota limit from the plan
- `$event->thresholdPercentage` - Warning threshold (QuotaWarning only)
- `$event->overage` - Amount over limit (QuotaExceeded only)

Configure warning thresholds in `config/billing.php`:

```php
'quota_warning_thresholds' => [80, 90, 95], // Fire warnings at these percentages
```

### API Routes

Include the API routes in your `routes/api.php`:

```php
Route::middleware('auth:sanctum')->group(function () {
    require __DIR__.'/../vendor/bhhaskin/laravel-billing/routes/api.php';
});
```

### Daily Billing Processing

The package automatically schedules a daily billing command to process renewals, trial expirations, and grace periods. You can disable this in config and run it manually:

```bash
php artisan billing:process
```

## Testing

```bash
composer test
```

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).
