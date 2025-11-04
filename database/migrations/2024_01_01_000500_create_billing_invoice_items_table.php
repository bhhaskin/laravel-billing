<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.invoice_items', 'billing_invoice_items'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->constrained(config('billing.tables.invoices', 'billing_invoices'))->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained(config('billing.tables.subscriptions', 'billing_subscriptions'))->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained(config('billing.tables.plans', 'billing_plans'))->nullOnDelete();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 10, 2);
            $table->boolean('is_proration')->default(false);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.invoice_items', 'billing_invoice_items'));
    }
};
