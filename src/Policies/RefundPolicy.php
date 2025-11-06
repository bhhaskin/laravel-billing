<?php

namespace Bhhaskin\Billing\Policies;

use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Refund;
use Illuminate\Contracts\Auth\Authenticatable;

class RefundPolicy
{
    /**
     * Determine if the user can view any refunds.
     */
    public function viewAny(Authenticatable $user): bool
    {
        // User can view their own refunds if they have a customer
        return $user->customer !== null;
    }

    /**
     * Determine if the user can view the refund.
     */
    public function view(Authenticatable $user, Refund $refund): bool
    {
        // User can view refund if they own the customer account
        return $user->customer && $user->customer->id === $refund->customer_id;
    }

    /**
     * Determine if the user can create a refund.
     */
    public function create(Authenticatable $user, Invoice $invoice): bool
    {
        // User can create refund if:
        // 1. They own the customer account
        // 2. Invoice is paid
        // 3. Invoice is not fully refunded
        return $user->customer
            && $user->customer->id === $invoice->customer_id
            && $invoice->isPaid()
            && ! $invoice->isFullyRefunded();
    }

    /**
     * Determine if the user can cancel the refund.
     */
    public function cancel(Authenticatable $user, Refund $refund): bool
    {
        // User can cancel refund if:
        // 1. They own the customer account
        // 2. Refund is still pending
        return $user->customer
            && $user->customer->id === $refund->customer_id
            && $refund->isPending();
    }

    /**
     * Determine if the user can update the refund.
     */
    public function update(Authenticatable $user, Refund $refund): bool
    {
        // Only pending refunds can be updated
        return $this->cancel($user, $refund);
    }

    /**
     * Determine if the user can delete the refund.
     */
    public function delete(Authenticatable $user, Refund $refund): bool
    {
        // Alias for cancel
        return $this->cancel($user, $refund);
    }
}
