<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Stripe API keys and configuration options.
    |
    */

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'api_version' => '2024-11-20',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The currency to use for billing. Defaults to USD.
    |
    */

    'currency' => env('BILLING_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Billable Model
    |--------------------------------------------------------------------------
    |
    | The model that represents a billable entity (usually User).
    | This model should use the Billable trait.
    |
    */

    'billable_model' => env('BILLING_BILLABLE_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Workspace Support
    |--------------------------------------------------------------------------
    |
    | Enable workspace-based billing if bhhaskin/laravel-workspaces is installed.
    |
    */

    'workspace_model' => env('BILLING_WORKSPACE_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Plan Defaults
    |--------------------------------------------------------------------------
    |
    | Default configuration options for plans. These can be overridden per plan.
    |
    */

    'plan_defaults' => [
        // How to handle cancellations
        // Options: 'end_of_period', 'immediate'
        'cancellation_behavior' => 'end_of_period',

        // How to handle upgrades/downgrades
        // Options: 'immediate', 'end_of_period'
        'change_behavior' => 'immediate',

        // Whether to prorate plan changes
        'prorate_changes' => true,

        // Whether to prorate cancellations
        'prorate_cancellations' => false,

        // Grace period in days after failed payment before suspending
        'grace_period_days' => 0,

        // Trial period in days (0 = no trial)
        'trial_period_days' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Payment Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for handling failed payments.
    |
    */

    'failed_payment' => [
        // Number of retry attempts
        'max_retries' => 3,

        // Days between retry attempts
        'retry_interval_days' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quota Warning Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the percentage thresholds at which quota warning events are fired.
    | For example, [80, 90] will fire QuotaWarning events at 80% and 90% usage.
    |
    */

    'quota_warning_thresholds' => [80, 90],

    /*
    |--------------------------------------------------------------------------
    | Daily Billing Task
    |--------------------------------------------------------------------------
    |
    | Automatically register the daily billing command in the scheduler.
    | If false, you must manually register it in your application's Kernel.
    |
    */

    'auto_register_scheduler' => env('BILLING_AUTO_REGISTER_SCHEDULER', true),

    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for invoice generation.
    |
    */

    'invoice' => [
        // Invoice number prefix
        'number_prefix' => env('BILLING_INVOICE_PREFIX', 'INV-'),

        // Starting invoice number
        'starting_number' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Handling
    |--------------------------------------------------------------------------
    |
    | Stripe webhook handling configuration. Idempotency is enforced by
    | recording each event's stripe_event_id in the webhook_events table;
    | duplicate deliveries are dropped without re-running handlers.
    |
    */

    'webhook' => [
        // Number of days to retain processed webhook event records before
        // billing:prune-webhook-events deletes them. Set to null to disable pruning.
        'retention_days' => env('BILLING_WEBHOOK_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Method Expiry Warnings
    |--------------------------------------------------------------------------
    |
    | When a card payment method is within this many days of expiry, the
    | billing:check-expiring-payment-methods command will dispatch a
    | PaymentMethodExpiring event. The event fires once per (card, expiry-month)
    | combination; tracking is stored on the payment method's metadata.
    |
    */

    'payment_method_expiry' => [
        'warning_days' => env('BILLING_PAYMENT_METHOD_EXPIRY_WARNING_DAYS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the table names used by the package.
    |
    */

    'tables' => [
        'customers' => 'billing_customers',
        'plans' => 'billing_plans',
        'subscriptions' => 'billing_subscriptions',
        'subscription_items' => 'billing_subscription_items',
        'invoices' => 'billing_invoices',
        'invoice_items' => 'billing_invoice_items',
        'payment_methods' => 'billing_payment_methods',
        'usage_records' => 'billing_usage_records',
        'quota_usage' => 'billing_quota_usage',
        'webhook_events' => 'billing_webhook_events',
    ],
];
