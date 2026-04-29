<?php

namespace Bhhaskin\Billing\Console\Commands;

use Bhhaskin\Billing\Models\WebhookEvent;
use Illuminate\Console\Command;

class PruneWebhookEventsCommand extends Command
{
    protected $signature = 'billing:prune-webhook-events
        {--days= : Override billing.webhook.retention_days}';

    protected $description = 'Delete processed Stripe webhook event records older than the retention window';

    public function handle(): int
    {
        $retentionDays = $this->option('days') !== null
            ? (int) $this->option('days')
            : config('billing.webhook.retention_days');

        if ($retentionDays === null || $retentionDays <= 0) {
            $this->info('Webhook retention disabled (retention_days is null or non-positive). Nothing to do.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($retentionDays);

        $deleted = WebhookEvent::query()
            ->whereNotNull('processed_at')
            ->where('processed_at', '<', $cutoff)
            ->delete();

        $this->info(sprintf('Pruned %d webhook event record(s) older than %d days.', $deleted, $retentionDays));

        return self::SUCCESS;
    }
}
