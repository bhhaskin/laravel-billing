<?php

namespace Bhhaskin\Billing;

use Bhhaskin\Billing\Console\Commands\ProcessBillingCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Stripe\Stripe;

class BillingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/billing.php',
            'billing'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePublishing();
        $this->configureMigrations();
        $this->configureCommands();
        $this->configureStripe();
        $this->configureScheduler();
    }

    /**
     * Configure publishing for the package.
     */
    protected function configurePublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/billing.php' => config_path('billing.php'),
            ], 'billing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'billing-migrations');

            $this->publishes([
                __DIR__ . '/../database/seeders' => database_path('seeders/billing'),
            ], 'billing-seeders');
        }
    }

    /**
     * Configure migrations for the package.
     */
    protected function configureMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Configure commands for the package.
     */
    protected function configureCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessBillingCommand::class,
            ]);
        }
    }

    /**
     * Configure Stripe.
     */
    protected function configureStripe(): void
    {
        if ($secret = config('billing.stripe.secret')) {
            Stripe::setApiKey($secret);

            if ($version = config('billing.stripe.api_version')) {
                Stripe::setApiVersion($version);
            }
        }
    }

    /**
     * Configure the scheduler for automatic billing processing.
     */
    protected function configureScheduler(): void
    {
        if (! config('billing.auto_register_scheduler', true)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('billing:process')->daily();
        });
    }
}
