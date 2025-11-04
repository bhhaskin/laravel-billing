<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentSucceeded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Invoice $invoice
    ) {
    }
}
