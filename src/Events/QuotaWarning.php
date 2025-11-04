<?php

namespace Bhhaskin\Billing\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuotaWarning
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $billable,
        public string $quotaKey,
        public int|float $currentUsage,
        public int|float $limit,
        public int $thresholdPercentage
    ) {
    }
}
