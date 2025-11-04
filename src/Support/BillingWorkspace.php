<?php

namespace Bhhaskin\Billing\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingWorkspace
{
    /**
     * Determine if workspace support is available.
     */
    public static function available(): bool
    {
        return ! empty(config('billing.workspace_model'));
    }

    /**
     * Get the workspace model class.
     */
    public static function model(): ?string
    {
        return config('billing.workspace_model');
    }

    /**
     * Create a workspace relation for the given model.
     */
    public static function relation(Model $model): BelongsTo
    {
        if (! static::available()) {
            throw new \RuntimeException('Workspace model is not configured');
        }

        return $model->belongsTo(static::model(), 'workspace_id');
    }

    /**
     * Guard that the model is a valid workspace model.
     */
    public static function guardModel(Model $workspace): void
    {
        $workspaceModel = static::model();

        if (! $workspaceModel) {
            throw new \RuntimeException('Workspace model is not configured');
        }

        if (! $workspace instanceof $workspaceModel) {
            throw new \InvalidArgumentException(
                sprintf('Expected instance of %s, got %s', $workspaceModel, get_class($workspace))
            );
        }
    }

    /**
     * Assign a workspace to a customer.
     */
    public static function assignToCustomer(Model $customer, ?Model $workspace): void
    {
        if (! static::available()) {
            return;
        }

        if ($workspace !== null) {
            static::guardModel($workspace);
        }

        $customer->workspace()->associate($workspace);
        $customer->save();
    }

    /**
     * Assign a workspace to a subscription.
     */
    public static function assignToSubscription(Model $subscription, ?Model $workspace): void
    {
        if (! static::available()) {
            return;
        }

        if ($workspace !== null) {
            static::guardModel($workspace);
        }

        $subscription->workspace()->associate($workspace);
        $subscription->save();
    }
}
