<?php

namespace Bhhaskin\Billing\Tests;

use Bhhaskin\Billing\BillingServiceProvider;
use Bhhaskin\Billing\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        $providers = [];

        // Support for laravel-audit if available
        if (class_exists(\LaravelAudit\AuditServiceProvider::class)) {
            $providers[] = \LaravelAudit\AuditServiceProvider::class;
        }

        $providers[] = BillingServiceProvider::class;

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        // Database configuration
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Auth configuration
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => User::class,
        ]);

        // Billing configuration
        $app['config']->set('billing.billable_model', User::class);
        $app['config']->set('billing.currency', 'usd');
        $app['config']->set('billing.auto_register_scheduler', false);

        // Disable Stripe API for tests (use mocking instead)
        $app['config']->set('billing.stripe.key', null);
        $app['config']->set('billing.stripe.secret', null);
    }

    protected function defineDatabaseMigrations(): void
    {
        // Load test migrations (for User model)
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Run package migrations
        $this->artisan('migrate', ['--database' => 'testbench'])->run();
    }
}
