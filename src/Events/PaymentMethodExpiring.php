<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\PaymentMethod;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentMethodExpiring
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public PaymentMethod $paymentMethod,
        public int $daysUntilExpiry,
    ) {
    }
}
