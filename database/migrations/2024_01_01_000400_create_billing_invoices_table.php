<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.invoices', 'billing_invoices'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained(config('billing.tables.customers', 'billing_customers'))->cascadeOnDelete();
            $table->string('stripe_id')->unique()->nullable();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('draft'); // 'draft', 'open', 'paid', 'void', 'uncollectible'
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('usd');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('due_date');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.invoices', 'billing_invoices'));
    }
};
