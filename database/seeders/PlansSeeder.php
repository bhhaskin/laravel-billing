<?php

namespace Bhhaskin\Billing\Database\Seeders;

use Bhhaskin\Billing\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Base hosting plans
        Plan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Perfect for small websites and blogs',
            'price' => 9.99,
            'interval' => Plan::INTERVAL_MONTHLY,
            'type' => Plan::TYPE_PLAN,
            'features' => [
                '1 Website',
                '10 GB Storage',
                '100 GB Bandwidth',
                'Free SSL Certificate',
                '24/7 Support',
            ],
            'limits' => [
                'websites' => 1,
                'storage_gb' => 10,
                'bandwidth_gb' => 100,
                'email_accounts' => 5,
            ],
            'sort_order' => 1,
        ]);

        Plan::create([
            'name' => 'Professional',
            'slug' => 'professional',
            'description' => 'For growing businesses and professional sites',
            'price' => 19.99,
            'interval' => Plan::INTERVAL_MONTHLY,
            'type' => Plan::TYPE_PLAN,
            'features' => [
                '5 Websites',
                '50 GB Storage',
                '500 GB Bandwidth',
                'Free SSL Certificate',
                'Priority Support',
                'Free Domain',
            ],
            'limits' => [
                'websites' => 5,
                'storage_gb' => 50,
                'bandwidth_gb' => 500,
                'email_accounts' => 25,
            ],
            'sort_order' => 2,
        ]);

        Plan::create([
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'Advanced features for business websites',
            'price' => 49.99,
            'interval' => Plan::INTERVAL_MONTHLY,
            'type' => Plan::TYPE_PLAN,
            'features' => [
                'Unlimited Websites',
                '200 GB Storage',
                'Unlimited Bandwidth',
                'Free SSL Certificate',
                'Priority Support',
                'Free Domain',
                'Dedicated IP',
            ],
            'limits' => [
                'websites' => null, // unlimited
                'storage_gb' => 200,
                'bandwidth_gb' => null, // unlimited
                'email_accounts' => null, // unlimited
            ],
            'sort_order' => 3,
        ]);

        // Yearly plans (discounted)
        Plan::create([
            'name' => 'Starter Yearly',
            'slug' => 'starter-yearly',
            'description' => 'Perfect for small websites and blogs - Save 20% yearly',
            'price' => 95.90, // ~$8/month
            'interval' => Plan::INTERVAL_YEARLY,
            'type' => Plan::TYPE_PLAN,
            'features' => [
                '1 Website',
                '10 GB Storage',
                '100 GB Bandwidth',
                'Free SSL Certificate',
                '24/7 Support',
            ],
            'limits' => [
                'websites' => 1,
                'storage_gb' => 10,
                'bandwidth_gb' => 100,
                'email_accounts' => 5,
            ],
            'sort_order' => 11,
        ]);

        Plan::create([
            'name' => 'Professional Yearly',
            'slug' => 'professional-yearly',
            'description' => 'For growing businesses - Save 20% yearly',
            'price' => 191.90, // ~$16/month
            'interval' => Plan::INTERVAL_YEARLY,
            'type' => Plan::TYPE_PLAN,
            'features' => [
                '5 Websites',
                '50 GB Storage',
                '500 GB Bandwidth',
                'Free SSL Certificate',
                'Priority Support',
                'Free Domain',
            ],
            'limits' => [
                'websites' => 5,
                'storage_gb' => 50,
                'bandwidth_gb' => 500,
                'email_accounts' => 25,
            ],
            'sort_order' => 12,
        ]);

        // Add-ons that require a plan
        Plan::create([
            'name' => 'Additional Storage',
            'slug' => 'additional-storage',
            'description' => 'Add 50 GB of extra storage to your plan',
            'price' => 5.00,
            'interval' => Plan::INTERVAL_MONTHLY,
            'type' => Plan::TYPE_ADDON,
            'requires_plan' => true,
            'limits' => [
                'storage_gb' => 50,
            ],
            'sort_order' => 101,
        ]);

        Plan::create([
            'name' => 'Daily Backups',
            'slug' => 'daily-backups',
            'description' => 'Automated daily backups with 30-day retention',
            'price' => 2.99,
            'interval' => Plan::INTERVAL_MONTHLY,
            'type' => Plan::TYPE_ADDON,
            'requires_plan' => true,
            'features' => [
                'Daily Automated Backups',
                '30-Day Backup Retention',
                'One-Click Restore',
            ],
            'sort_order' => 102,
        ]);

        // Standalone add-ons (can be purchased without a plan)
        Plan::create([
            'name' => 'Email Hosting',
            'slug' => 'email-hosting',
            'description' => 'Professional email hosting with 10 mailboxes',
            'price' => 4.99,
            'interval' => Plan::INTERVAL_MONTHLY,
            'type' => Plan::TYPE_ADDON,
            'requires_plan' => false,
            'features' => [
                '10 Email Accounts',
                '25 GB Email Storage',
                'Webmail Access',
                'IMAP/POP3 Support',
            ],
            'limits' => [
                'email_accounts' => 10,
                'email_storage_gb' => 25,
            ],
            'sort_order' => 201,
        ]);

        Plan::create([
            'name' => 'Domain Registration',
            'slug' => 'domain-registration',
            'description' => 'Register a new domain for one year',
            'price' => 12.99,
            'interval' => Plan::INTERVAL_YEARLY,
            'type' => Plan::TYPE_ADDON,
            'requires_plan' => false,
            'features' => [
                'Domain Registration',
                'Free DNS Management',
                'Domain Privacy Protection',
            ],
            'sort_order' => 202,
        ]);
    }
}
