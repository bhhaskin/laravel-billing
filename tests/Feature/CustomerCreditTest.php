<?php

namespace Bhhaskin\Billing\Tests\Feature;

use Bhhaskin\Billing\Events\CreditAdded;
use Bhhaskin\Billing\Events\CreditApplied;
use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\CustomerCredit;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CustomerCreditTest extends TestCase
{
    /** @test */
    public function it_can_add_credit_to_customer()
    {
        $customer = Customer::factory()->create();

        $credit = $customer->addCredit(50.00, CustomerCredit::TYPE_PROMOTIONAL, 'Welcome bonus');

        $this->assertDatabaseHas('billing_customer_credits', [
            'customer_id' => $customer->id,
            'amount' => 50.00,
            'type' => CustomerCredit::TYPE_PROMOTIONAL,
            'description' => 'Welcome bonus',
        ]);
    }

    /** @test */
    public function it_fires_event_when_credit_added()
    {
        $customer = Customer::factory()->create();

        Event::fake([CreditAdded::class]);

        $customer->addCredit(25.00);

        Event::assertDispatched(CreditAdded::class);
    }

    /** @test */
    public function it_tracks_balance_before_and_after()
    {
        $customer = Customer::factory()->create();

        $customer->addCredit(30.00);
        $credit = $customer->addCredit(20.00);

        $this->assertEquals(30.00, $credit->balance_before);
        $this->assertEquals(50.00, $credit->balance_after);
    }

    /** @test */
    public function it_updates_customer_credit_balance()
    {
        $customer = Customer::factory()->create();

        $customer->addCredit(25.00);
        $customer->addCredit(35.00);

        $customer->updateCreditBalance();

        $this->assertEquals(60.00, $customer->fresh()->getAvailableCredit());
    }

    /** @test */
    public function it_can_deduct_credit()
    {
        $customer = Customer::factory()->create();
        $customer->addCredit(100.00);

        $debit = $customer->deductCredit(30.00);

        $this->assertEquals(-30.00, $debit->amount);
        $this->assertEquals(70.00, $customer->fresh()->getAvailableCredit());
    }

    /** @test */
    public function it_applies_credits_to_invoice()
    {
        $customer = Customer::factory()->create();
        $customer->addCredit(50.00);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subtotal' => 100.00,
            'total' => 100.00,
        ]);

        // Add an invoice item so calculateTotals() works correctly
        $invoice->items()->create([
            'description' => 'Test Item',
            'quantity' => 1,
            'unit_price' => 100.00,
            'amount' => 100.00,
        ]);

        $appliedAmount = $customer->applyCreditsToInvoice($invoice);

        $this->assertEquals(50.00, $appliedAmount);
        $this->assertEquals(0.00, $customer->fresh()->getAvailableCredit());
        $this->assertEquals(50.00, $invoice->fresh()->total);
    }

    /** @test */
    public function it_fires_event_when_credit_applied_to_invoice()
    {
        $customer = Customer::factory()->create();
        $customer->addCredit(25.00);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
        ]);

        Event::fake([CreditApplied::class, CreditAdded::class]);

        $customer->applyCreditsToInvoice($invoice);

        Event::assertDispatched(CreditApplied::class);
    }

    /** @test */
    public function it_only_applies_available_credit_to_invoice()
    {
        $customer = Customer::factory()->create();
        $customer->addCredit(30.00);

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'subtotal' => 100.00,
            'total' => 100.00,
        ]);

        // Add an invoice item so calculateTotals() works correctly
        $invoice->items()->create([
            'description' => 'Test Item',
            'quantity' => 1,
            'unit_price' => 100.00,
            'amount' => 100.00,
        ]);

        $appliedAmount = $customer->applyCreditsToInvoice($invoice);

        $this->assertEquals(30.00, $appliedAmount);
        $this->assertEquals(70.00, $invoice->fresh()->total);
    }

    /** @test */
    public function it_doesnt_apply_credit_if_no_balance()
    {
        $customer = Customer::factory()->create();

        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
        ]);

        $appliedAmount = $customer->applyCreditsToInvoice($invoice);

        $this->assertEquals(0, $appliedAmount);
        $this->assertEquals(100.00, $invoice->fresh()->total);
    }

    /** @test */
    public function it_tracks_credit_expiration()
    {
        $customer = Customer::factory()->create();

        $expired = CustomerCredit::factory()->expired()->create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
        ]);

        $active = CustomerCredit::factory()->active()->create([
            'customer_id' => $customer->id,
            'amount' => 30.00,
        ]);

        $this->assertFalse($expired->isActive());
        $this->assertTrue($active->isActive());
    }

    /** @test */
    public function it_excludes_expired_credits_from_balance()
    {
        $customer = Customer::factory()->create();

        CustomerCredit::factory()->expired()->create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
        ]);

        CustomerCredit::factory()->active()->create([
            'customer_id' => $customer->id,
            'amount' => 30.00,
        ]);

        $customer->updateCreditBalance();

        $this->assertEquals(30.00, $customer->getAvailableCredit());
    }

    /** @test */
    public function it_distinguishes_credits_from_debits()
    {
        $customer = Customer::factory()->create();

        $credit = CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
        ]);

        $debit = CustomerCredit::factory()->invoicePayment()->create([
            'customer_id' => $customer->id,
            'amount' => -20.00,
        ]);

        $this->assertTrue($credit->isCredit());
        $this->assertFalse($credit->isDebit());

        $this->assertTrue($debit->isDebit());
        $this->assertFalse($debit->isCredit());
    }

    /** @test */
    public function it_filters_credits_by_type()
    {
        $customer = Customer::factory()->create();

        CustomerCredit::factory()->promotional()->create([
            'customer_id' => $customer->id,
            'amount' => 25.00,
        ]);

        CustomerCredit::factory()->refund()->create([
            'customer_id' => $customer->id,
            'amount' => 50.00,
        ]);

        $promotional = $customer->credits()->ofType(CustomerCredit::TYPE_PROMOTIONAL)->sum('amount');
        $refund = $customer->credits()->ofType(CustomerCredit::TYPE_REFUND)->sum('amount');

        $this->assertEquals(25.00, $promotional);
        $this->assertEquals(50.00, $refund);
    }
}
