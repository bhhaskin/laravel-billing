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
        'unit_price' => 10.00,
        'amount' => 20.00,
    ]);

    InvoiceItem::factory()->create([
        'invoice_id' => $invoice->id,
        'quantity' => 1,
        'unit_price' => 5.00,
        'amount' => 5.00,
    ]);

    $invoice->refresh();

    expect($invoice->subtotal)->toBe('25.00')
        ->and($invoice->total)->toBe('25.00');
});

test('can mark invoice as paid', function () {
    $invoice = Invoice::factory()->open()->create();

    $invoice->markAsPaid();

    expect($invoice->isPaid())->toBeTrue()
        ->and($invoice->paid_at)->not->toBeNull();
});
