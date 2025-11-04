<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCanceled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Subscription $subscription
    ) {
    }
}
