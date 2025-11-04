<?php

namespace Bhhaskin\Billing\Models;

use Bhhaskin\Billing\Database\Factories\QuotaUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $billable_type
 * @property int $billable_id
 * @property string $quota_key
 * @property float $current_usage
 * @property array|null $warning_thresholds_triggered
 * @property \Carbon\Carbon|null $last_exceeded_at
 */
class QuotaUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'billable_type',
        'billable_id',
        'quota_key',
        'current_usage',
        'warning_thresholds_triggered',
        'last_exceeded_at',
    ];

    protected $casts = [
        'current_usage' => 'float',
        'warning_thresholds_triggered' => 'array',
        'last_exceeded_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('billing.tables.quota_usage', 'billing_quota_usage');
    }

    protected static function booted(): void
    {
        static::creating(function (self $quotaUsage) {
            if (empty($quotaUsage->uuid)) {
                $quotaUsage->uuid = (string) Str::uuid();
            }
        });
    }

    protected static function newFactory(): QuotaUsageFactory
    {
        return QuotaUsageFactory::new();
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Reset warning thresholds that have been triggered.
     */
    public function resetWarnings(): void
    {
        $this->update(['warning_thresholds_triggered' => []]);
    }

    /**
     * Check if a specific warning threshold has been triggered.
     */
    public function hasTriggeredWarning(int $threshold): bool
    {
        return in_array($threshold, $this->warning_thresholds_triggered ?? []);
    }

    /**
     * Mark a warning threshold as triggered.
     */
    public function markWarningTriggered(int $threshold): void
    {
        $triggered = $this->warning_thresholds_triggered ?? [];
        if (! in_array($threshold, $triggered)) {
            $triggered[] = $threshold;
            $this->update(['warning_thresholds_triggered' => $triggered]);
        }
    }
}
