<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\CustomerCredit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CustomerCredit $credit
    ) {
    }
}
