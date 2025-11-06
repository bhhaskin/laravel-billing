<?php

namespace Bhhaskin\Billing\Tests\Feature;

use Bhhaskin\Billing\Events\RefundCreated;
use Bhhaskin\Billing\Events\RefundSucceeded;
use Bhhaskin\Billing\Models\Customer;
use Bhhaskin\Billing\Models\CustomerCredit;
use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\Refund;
use Bhhaskin\Billing\Tests\Fixtures\User;
use Bhhaskin\Billing\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class RefundTest extends TestCase
{
    /** @test */
    public function it_can_create_a_refund()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $refund = $customer->createRefund(50.00, $invoice, Refund::REASON_REQUESTED_BY_CUSTOMER);

        $this->assertDatabaseHas('billing_refunds', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 50.00,
            'status' => Refund::STATUS_PENDING,
        ]);
    }

    /** @test */
    public function it_fires_event_when_refund_created()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);

        Event::fake([RefundCreated::class]);

        $customer->createRefund(25.00, $invoice);

        Event::assertDispatched(RefundCreated::class);
    }

    /** @test */
    public function it_can_mark_refund_as_succeeded()
    {
        $refund = Refund::factory()->pending()->create();

        $refund->markAsSucceeded();

        $this->assertEquals(Refund::STATUS_SUCCEEDED, $refund->status);
        $this->assertNotNull($refund->processed_at);
    }

    /** @test */
    public function it_fires_event_when_refund_succeeds()
    {
        $refund = Refund::factory()->pending()->create();

        Event::fake([RefundSucceeded::class, CreditAdded::class]);

        $refund->markAsSucceeded();

        Event::assertDispatched(RefundSucceeded::class);
    }

    /** @test */
    public function it_creates_customer_credit_on_successful_refund()
    {
        $customer = Customer::factory()->create();
        $refund = Refund::factory()->pending()->create([
            'customer_id' => $customer->id,
            'amount' => 75.00,
        ]);

        $refund->markAsSucceeded();

        $this->assertDatabaseHas('billing_customer_credits', [
            'customer_id' => $customer->id,
            'refund_id' => $refund->id,
            'amount' => 75.00,
            'type' => CustomerCredit::TYPE_REFUND,
        ]);
    }

    /** @test */
    public function it_can_refund_full_invoice_amount()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $refund = $invoice->refund();

        $this->assertEquals(100.00, $refund->amount);
    }

    /** @test */
    public function it_can_refund_partial_invoice_amount()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $refund = $invoice->refund(40.00);

        $this->assertEquals(40.00, $refund->amount);
    }

    /** @test */
    public function it_prevents_refunding_more_than_remaining_amount()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        // First refund
        $invoice->refund(60.00)->markAsSucceeded();

        $this->expectException(\InvalidArgumentException::class);

        // Try to refund more than remaining
        $invoice->refund(50.00);
    }

    /** @test */
    public function it_tracks_total_refunded_amount()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $invoice->refund(30.00)->markAsSucceeded();
        $invoice->refund(20.00)->markAsSucceeded();

        $this->assertEquals(50.00, $invoice->getTotalRefunded());
        $this->assertEquals(50.00, $invoice->getRemainingRefundable());
    }

    /** @test */
    public function it_detects_fully_refunded_invoice()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $invoice->refund(100.00)->markAsSucceeded();

        $this->assertTrue($invoice->isFullyRefunded());
        $this->assertFalse($invoice->isPartiallyRefunded());
    }

    /** @test */
    public function it_detects_partially_refunded_invoice()
    {
        $customer = Customer::factory()->create();
        $invoice = Invoice::factory()->create([
            'customer_id' => $customer->id,
            'total' => 100.00,
            'status' => Invoice::STATUS_PAID,
        ]);

        $invoice->refund(40.00)->markAsSucceeded();

        $this->assertFalse($invoice->isFullyRefunded());
        $this->assertTrue($invoice->isPartiallyRefunded());
    }

    /** @test */
    public function it_can_cancel_pending_refund()
    {
        $refund = Refund::factory()->pending()->create();

        $refund->cancel();

        $this->assertEquals(Refund::STATUS_CANCELED, $refund->status);
        $this->assertNotNull($refund->processed_at);
    }

    /** @test */
    public function it_cannot_cancel_non_pending_refund()
    {
        $refund = Refund::factory()->succeeded()->create();

        $this->expectException(\RuntimeException::class);

        $refund->cancel();
    }

    /** @test */
    public function refund_statuses_work_correctly()
    {
        $pending = Refund::factory()->pending()->create();
        $succeeded = Refund::factory()->succeeded()->create();
        $failed = Refund::factory()->failed()->create();

        $this->assertTrue($pending->isPending());
        $this->assertFalse($pending->isSucceeded());

        $this->assertTrue($succeeded->isSucceeded());
        $this->assertFalse($succeeded->isPending());

        $this->assertTrue($failed->isFailed());
        $this->assertFalse($failed->isSucceeded());
    }
}
