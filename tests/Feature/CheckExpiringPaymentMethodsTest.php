<?php

namespace Bhhaskin\Billing\Tests\Feature;

use Bhhaskin\Billing\Events\PaymentMethodExpiring;
use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\PaymentMethod;
use Bhhaskin\Billing\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CheckExpiringPaymentMethodsTest extends TestCase
{
    /** @test */
    public function it_dispatches_event_for_a_card_within_the_warning_window(): void
    {
        Event::fake([PaymentMethodExpiring::class]);

        $customer = Customer::factory()->create();
        $expirySoon = now()->addDays(45);
        $pm = PaymentMethod::factory()->create([
            'customer_id' => $customer->id,
            'exp_month' => str_pad((string) $expirySoon->month, 2, '0', STR_PAD_LEFT),
            'exp_year' => (string) $expirySoon->year,
        ]);

        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 90])
            ->assertExitCode(0);

        Event::assertDispatched(PaymentMethodExpiring::class, function ($event) use ($pm) {
            return $event->paymentMethod->id === $pm->id
                && $event->daysUntilExpiry >= 0;
        });
    }

    /** @test */
    public function it_does_not_dispatch_for_a_card_expiring_outside_the_window(): void
    {
        Event::fake([PaymentMethodExpiring::class]);

        $customer = Customer::factory()->create();
        $expiry = now()->addYear()->endOfMonth();
        PaymentMethod::factory()->create([
            'customer_id' => $customer->id,
            'exp_month' => str_pad((string) $expiry->month, 2, '0', STR_PAD_LEFT),
            'exp_year' => (string) $expiry->year,
        ]);

        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 90])
            ->assertExitCode(0);

        Event::assertNotDispatched(PaymentMethodExpiring::class);
    }

    /** @test */
    public function it_does_not_dispatch_for_an_already_expired_card(): void
    {
        Event::fake([PaymentMethodExpiring::class]);

        $customer = Customer::factory()->create();
        PaymentMethod::factory()->expired()->create([
            'customer_id' => $customer->id,
        ]);

        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 60])
            ->assertExitCode(0);

        Event::assertNotDispatched(PaymentMethodExpiring::class);
    }

    /** @test */
    public function it_skips_a_card_already_warned_for_the_same_expiry(): void
    {
        $customer = Customer::factory()->create();
        $expirySoon = now()->addDays(45);
        $pm = PaymentMethod::factory()->create([
            'customer_id' => $customer->id,
            'exp_month' => str_pad((string) $expirySoon->month, 2, '0', STR_PAD_LEFT),
            'exp_year' => (string) $expirySoon->year,
        ]);

        // First run should dispatch and stamp metadata
        Event::fake([PaymentMethodExpiring::class]);
        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 90])->assertExitCode(0);
        Event::assertDispatchedTimes(PaymentMethodExpiring::class, 1);

        $pm->refresh();
        $this->assertNotNull($pm->metadata['expiry_warning']['dispatched_at'] ?? null);

        // Second run should skip
        Event::fake([PaymentMethodExpiring::class]);
        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 90])->assertExitCode(0);
        Event::assertNotDispatched(PaymentMethodExpiring::class);
    }

    /** @test */
    public function it_redispatches_with_force_flag(): void
    {
        $customer = Customer::factory()->create();
        $expirySoon = now()->addDays(45);
        $pm = PaymentMethod::factory()->create([
            'customer_id' => $customer->id,
            'exp_month' => str_pad((string) $expirySoon->month, 2, '0', STR_PAD_LEFT),
            'exp_year' => (string) $expirySoon->year,
            'metadata' => [
                'expiry_warning' => [
                    'dispatched_at' => now()->subDays(5)->toIso8601String(),
                    'exp_year_month' => $expirySoon->year . '-' . str_pad((string) $expirySoon->month, 2, '0', STR_PAD_LEFT),
                ],
            ],
        ]);

        Event::fake([PaymentMethodExpiring::class]);

        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 90, '--force' => true])
            ->assertExitCode(0);

        Event::assertDispatched(PaymentMethodExpiring::class, fn ($event) => $event->paymentMethod->id === $pm->id);
    }

    /** @test */
    public function dry_run_does_not_dispatch_or_update_metadata(): void
    {
        Event::fake([PaymentMethodExpiring::class]);

        $customer = Customer::factory()->create();
        $expirySoon = now()->addDays(45);
        $pm = PaymentMethod::factory()->create([
            'customer_id' => $customer->id,
            'exp_month' => str_pad((string) $expirySoon->month, 2, '0', STR_PAD_LEFT),
            'exp_year' => (string) $expirySoon->year,
        ]);

        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 30, '--dry-run' => true])
            ->assertExitCode(0);

        Event::assertNotDispatched(PaymentMethodExpiring::class);

        $pm->refresh();
        $this->assertNull($pm->metadata['expiry_warning'] ?? null);
    }

    /** @test */
    public function it_ignores_non_card_payment_methods(): void
    {
        Event::fake([PaymentMethodExpiring::class]);

        $customer = Customer::factory()->create();
        PaymentMethod::factory()->bankAccount()->create([
            'customer_id' => $customer->id,
        ]);

        $this->artisan('billing:check-expiring-payment-methods', ['--days' => 90])
            ->assertExitCode(0);

        Event::assertNotDispatched(PaymentMethodExpiring::class);
    }
}
