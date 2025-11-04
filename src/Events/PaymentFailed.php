<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public ?string $reason = null
    ) {
    }
}
