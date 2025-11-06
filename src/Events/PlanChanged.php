<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\Plan;
use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public Plan $oldPlan,
        public Plan $newPlan,
        public bool $prorated = false
    ) {
    }
}
