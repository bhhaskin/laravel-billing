<?php

namespace Bhhaskin\Billing\Policies;

use Bhhaskin\Billing\Models\CustomerCredit;
use Illuminate\Contracts\Auth\Authenticatable;

class CreditPolicy
{
    /**
     * Determine if the user can view any credits.
     */
    public function viewAny(Authenticatable $user): bool
    {
        // User can view their own credits if they have a customer
        return $user->customer !== null;
    }

    /**
     * Determine if the user can view the credit.
     */
    public function view(Authenticatable $user, CustomerCredit $credit): bool
    {
        // User can view credit if they own the customer account
        return $user->customer && $user->customer->id === $credit->customer_id;
    }

    /**
     * Determine if the user can view their credit balance.
     */
    public function viewBalance(Authenticatable $user): bool
    {
        return $user->customer !== null;
    }

    /**
     * Determine if the user can view credit summary.
     */
    public function viewSummary(Authenticatable $user): bool
    {
        return $user->customer !== null;
    }
}
