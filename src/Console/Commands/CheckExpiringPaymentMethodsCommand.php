<?php

namespace Bhhaskin\Billing\Console\Commands;

use Bhhaskin\Billing\Events\PaymentMethodExpiring;
use Bhhaskin\Billing\Models\PaymentMethod;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckExpiringPaymentMethodsCommand extends Command
{
    protected $signature = 'billing:check-expiring-payment-methods
        {--days= : Override billing.payment_method_expiry.warning_days}
        {--force : Re-dispatch even if a warning was already sent for this expiry}
        {--dry-run : List affected payment methods without dispatching events}';

    protected $description = 'Dispatch PaymentMethodExpiring for cards within the warning window';

    public function handle(): int
    {
        $warningDays = (int) ($this->option('days') ?? config('billing.payment_method_expiry.warning_days', 60));

        if ($warningDays <= 0) {
            $this->warn('warning_days is non-positive; nothing to do.');
            return self::SUCCESS;
        }

        $threshold = now()->addDays($warningDays)->endOfDay();
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $candidates = PaymentMethod::query()
            ->where('type', PaymentMethod::TYPE_CARD)
            ->whereNotNull('exp_month')
            ->whereNotNull('exp_year')
            ->get()
            ->filter(function (PaymentMethod $pm) use ($threshold) {
                $expiry = $this->cardExpiryDate($pm);
                return $expiry !== null
                    && $expiry->lessThanOrEqualTo($threshold)
                    && $expiry->greaterThan(now());
            });

        if ($candidates->isEmpty()) {
            $this->info('No payment methods within the expiry warning window.');
            return self::SUCCESS;
        }

        $dispatched = 0;
        $skipped = 0;

        foreach ($candidates as $pm) {
            $expiry = $this->cardExpiryDate($pm);
            $expiryKey = $pm->exp_year . '-' . str_pad((string) $pm->exp_month, 2, '0', STR_PAD_LEFT);
            $alreadyWarnedFor = $pm->metadata['expiry_warning']['exp_year_month'] ?? null;
            $days = (int) now()->startOfDay()->diffInDays($expiry, false);

            if (! $force && $alreadyWarnedFor === $expiryKey) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] PaymentMethod #%d (%s **** %s) expires in %d days',
                    $pm->id,
                    $pm->brand ?? 'card',
                    $pm->last_four ?? '????',
                    $days
                ));
                continue;
            }

            event(new PaymentMethodExpiring($pm, $days));

            $metadata = $pm->metadata ?? [];
            $metadata['expiry_warning'] = [
                'dispatched_at' => now()->toIso8601String(),
                'exp_year_month' => $expiryKey,
                'days_until_expiry' => $days,
            ];
            $pm->update(['metadata' => $metadata]);

            $dispatched++;
        }

        if ($dryRun) {
            $this->info(sprintf('Dry run complete. %d payment method(s) within %d days of expiry.', $candidates->count(), $warningDays));
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Dispatched PaymentMethodExpiring for %d payment method(s); skipped %d already warned.',
            $dispatched,
            $skipped
        ));

        return self::SUCCESS;
    }

    /**
     * Compute the last moment of the card's expiry month (cards are valid through end of expiry month).
     */
    protected function cardExpiryDate(PaymentMethod $pm): ?Carbon
    {
        $year = (int) $pm->exp_year;
        $month = (int) $pm->exp_month;

        if ($month < 1 || $month > 12 || $year < 2000) {
            return null;
        }

        return Carbon::createFromDate($year, $month, 1)->endOfMonth();
    }
}
