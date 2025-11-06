<?php

namespace Bhhaskin\Billing\Policies;

use Bhhaskin\Billing\Models\Invoice;
use Illuminate\Contracts\Auth\Authenticatable;

class InvoicePolicy
{
    /**
     * Determine if the user can view any invoices.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->customer !== null;
    }

    /**
     * Determine if the user can view the invoice.
     */
    public function view(Authenticatable $user, Invoice $invoice): bool
    {
        return $user->customer && $user->customer->id === $invoice->customer_id;
    }

    /**
     * Determine if the user can refund the invoice.
     */
    public function refund(Authenticatable $user, Invoice $invoice): bool
    {
        return $user->customer
            && $user->customer->id === $invoice->customer_id
            && $invoice->isPaid()
            && ! $invoice->isFullyRefunded();
    }
}
