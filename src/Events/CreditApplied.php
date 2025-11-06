<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\CustomerCredit;
use Bhhaskin\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerCredit $credit,
        public Invoice $invoice,
        public float $amountApplied
    ) {
    }
}
