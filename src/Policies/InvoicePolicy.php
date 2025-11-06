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
        return $this->userOwnsInvoice($user, $invoice);
    }

    /**
     * Determine if the user can download the invoice.
     */
    public function download(Authenticatable $user, Invoice $invoice): bool
    {
        return $this->userOwnsInvoice($user, $invoice);
    }

    /**
     * Determine if the user can refund the invoice.
     */
    public function refund(Authenticatable $user, Invoice $invoice): bool
    {
        return $this->userOwnsInvoice($user, $invoice)
            && $invoice->isPaid()
            && ! $invoice->isFullyRefunded();
    }

    /**
     * Check if the user owns the invoice through their customer record.
     */
    protected function userOwnsInvoice(Authenticatable $user, Invoice $invoice): bool
    {
        // Get the invoice's customer
        $customer = $invoice->customer;

        if (!$customer) {
            return false;
        }

        // Check if the customer's billable model matches the authenticated user
        return $customer->billable_type === get_class($user)
            && $customer->billable_id === $user->getAuthIdentifier();
    }
}
