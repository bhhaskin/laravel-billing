<?php

namespace Bhhaskin\Billing\Console\Commands;

use Bhhaskin\Billing\Models\Subscription;
use Illuminate\Console\Command;

class ProcessBillingCommand extends Command
{
    protected $signature = 'billing:process';

    protected $description = 'Process billing for all active subscriptions';

    public function handle(): int
    {
        $this->info('Processing billing...');

        // Process subscriptions that need renewal
        $this->processRenewals();

        // Handle trial expirations
        $this->processTrialExpirations();

        // Handle grace period expirations
        $this->processGracePeriodExpirations();

        // Clean up ended subscriptions
        $this->cleanupEndedSubscriptions();

        $this->info('Billing processing complete.');

        return self::SUCCESS;
    }

    /**
     * Process subscription renewals.
     */
    protected function processRenewals(): void
    {
        $subscriptions = Subscription::whereIn('status', [
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_TRIALING,
        ])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->line('No subscriptions to renew.');
            return;
        }

        $this->line("Processing {$subscriptions->count()} subscription renewals...");

        foreach ($subscriptions as $subscription) {
            try {
                // In a real implementation, this would trigger Stripe billing
                // For now, we'll just update the periods
                $subscription->update([
                    'current_period_start' => $subscription->current_period_end,
                    'current_period_end' => $subscription->current_period_end->addMonth(),
                ]);

                $this->line("✓ Renewed subscription {$subscription->uuid}");
            } catch (\Exception $e) {
                $this->error("✗ Failed to renew subscription {$subscription->uuid}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Process trial expirations.
     */
    protected function processTrialExpirations(): void
    {
        $subscriptions = Subscription::where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->line('No trial expirations to process.');
            return;
        }

        $this->line("Processing {$subscriptions->count()} trial expirations...");

        foreach ($subscriptions as $subscription) {
            try {
                // Check if customer has a payment method
                $hasPaymentMethod = $subscription->customer->paymentMethods()->exists();

                if ($hasPaymentMethod) {
                    $subscription->update(['status' => Subscription::STATUS_ACTIVE]);
                    $this->line("✓ Activated subscription {$subscription->uuid}");
                } else {
                    $subscription->update(['status' => Subscription::STATUS_INCOMPLETE]);
                    $this->line("⚠ Subscription {$subscription->uuid} marked as incomplete (no payment method)");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to process trial expiration for {$subscription->uuid}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Process grace period expirations.
     */
    protected function processGracePeriodExpirations(): void
    {
        $subscriptions = Subscription::where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('last_failed_payment_at')
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->line('No grace periods to check.');
            return;
        }

        $this->line("Checking {$subscriptions->count()} past due subscriptions...");

        foreach ($subscriptions as $subscription) {
            try {
                // Get the primary plan's grace period
                $gracePeriodDays = 0;
                foreach ($subscription->items as $item) {
                    if ($item->plan->grace_period_days > $gracePeriodDays) {
                        $gracePeriodDays = $item->plan->grace_period_days;
                    }
                }

                if ($gracePeriodDays === 0) {
                    continue; // No grace period configured
                }

                $graceExpiresAt = $subscription->last_failed_payment_at->addDays($gracePeriodDays);

                if (now()->greaterThan($graceExpiresAt)) {
                    $subscription->update(['status' => Subscription::STATUS_SUSPENDED]);
                    $this->line("✓ Suspended subscription {$subscription->uuid} (grace period expired)");
                }
            } catch (\Exception $e) {
                $this->error("✗ Failed to process grace period for {$subscription->uuid}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Clean up ended subscriptions.
     */
    protected function cleanupEndedSubscriptions(): void
    {
        $subscriptions = Subscription::where('status', Subscription::STATUS_CANCELED)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->line('No ended subscriptions to cleanup.');
            return;
        }

        $this->line("Processing {$subscriptions->count()} ended subscriptions...");

        foreach ($subscriptions as $subscription) {
            // Subscription items are already marked with ends_at
            // This is mainly for any additional cleanup needed
            $this->line("✓ Processed ended subscription {$subscription->uuid}");
        }
    }
}
