<?php

namespace Bhhaskin\Billing\Support;

class BillableUserResolver
{
    /**
     * Resolve the billable user model.
     */
    public static function resolveModel(): string
    {
        return config('billing.billable_model', 'App\\Models\\User');
    }
}
