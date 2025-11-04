<?php

namespace Bhhaskin\Billing\Support;

use Illuminate\Database\Eloquent\Model;

class BillingAudit
{
    /**
     * Determine if audit support is available.
     */
    public static function available(): bool
    {
        return class_exists(\LaravelAudit\AuditServiceProvider::class);
    }

    /**
     * Record an audit trail for a billing model.
     */
    public static function record(Model $model, string $event, array $metadata = []): void
    {
        if (! static::available()) {
            return;
        }

        if (! method_exists($model, 'recordAudit')) {
            return;
        }

        $model->recordAudit($event, $metadata);
    }

    /**
     * Record a subscription change.
     */
    public static function recordSubscriptionChange(Model $subscription, string $action, array $metadata = []): void
    {
        static::record($subscription, 'subscription.' . $action, $metadata);
    }

    /**
     * Record an invoice action.
     */
    public static function recordInvoiceAction(Model $invoice, string $action, array $metadata = []): void
    {
        static::record($invoice, 'invoice.' . $action, $metadata);
    }

    /**
     * Record a payment action.
     */
    public static function recordPaymentAction(Model $model, string $action, array $metadata = []): void
    {
        static::record($model, 'payment.' . $action, $metadata);
    }

    /**
     * Record a plan change.
     */
    public static function recordPlanChange(Model $subscription, Model $plan, string $action, array $metadata = []): void
    {
        static::record($subscription, 'plan.' . $action, array_merge([
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
        ], $metadata));
    }
}
