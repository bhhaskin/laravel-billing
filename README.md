# Laravel Billing

Stripe-based subscription and billing management for Laravel applications.

## Features

- 🎯 **Multiple Plans & Add-ons** - Support for base plans and both standalone and plan-dependent add-ons
- 💳 **Stripe Integration** - Full Stripe integration with webhook support
- 🔄 **Proration** - Configurable proration for plan changes and cancellations
- 🎁 **Trial Periods** - Optional trial periods per plan
- ⏰ **Grace Periods** - Configurable grace periods for failed payments
- 📊 **Usage-Based Billing** - Track and bill for metered usage
- 📈 **Quota Tracking** - Track usage against limits with automatic warnings and exceeded events
- 🏢 **Workspace Support** - Optional multi-tenancy with bhhaskin/laravel-workspaces
- 📝 **Audit Trail** - Optional audit logging with bhhaskin/laravel-audit
- 🧪 **Fully Tested** - Comprehensive test suite with Pest
- 🔐 **UUIDs** - All models include UUIDs for secure API endpoints

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
