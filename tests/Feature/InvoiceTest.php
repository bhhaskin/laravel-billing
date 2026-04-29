<?php

use Bhhaskin\Billing\Models\Invoice;
use Bhhaskin\Billing\Models\InvoiceItem;

test('can create an invoice', function () {
    $invoice = Invoice::factory()->create();

    expect($invoice->uuid)->not->toBeNull()
        ->and($invoice->invoice_number)->not->toBeNull()
        ->and($invoice->status)->toBe(Invoice::STATUS_DRAFT);
});

test('invoice calculates totals', function () {
    $invoice = Invoice::factory()->create();

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 2,
        'unit_price' => 1000, // $10.00 in cents
        'amount' => 2000,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 1,
        'unit_price' => 500, // $5.00 in cents
        'amount' => 500,
    ]);

    $invoice->refresh();

    expect($invoice->subtotal)->toBe(2500)
        ->and($invoice->total)->toBe(2500);
});

test('can mark invoice as paid', function () {
    $invoice = Invoice::factory()->open()->create();

    $invoice->markAsPaid();

    expect($invoice->isPaid())->toBeTrue()
        ->and($invoice->paid_at)->not->toBeNull();
});
