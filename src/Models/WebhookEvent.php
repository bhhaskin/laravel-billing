<?php

namespace Bhhaskin\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $stripe_event_id
 * @property string $type
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 */
class WebhookEvent extends Model
{
    protected $fillable = [
        'stripe_event_id',
        'type',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('billing.tables.webhook_events', 'billing_webhook_events');
    }
}
