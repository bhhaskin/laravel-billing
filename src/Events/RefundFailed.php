<?php

namespace Bhhaskin\Billing\Events;

use Bhhaskin\Billing\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RefundFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Refund $refund,
        public ?string $reason = null
    ) {
    }
}
