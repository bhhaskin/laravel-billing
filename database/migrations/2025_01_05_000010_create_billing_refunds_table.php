<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('customer_id')
                ->constrained('billing_customers')
                ->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()
                ->constrained('billing_invoices')
                ->onDelete('set null');

            // Stripe integration
            $table->string('stripe_refund_id')->nullable()->unique();

            // Refund details
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'canceled'])->default('pending');
            $table->enum('reason', ['duplicate', 'fraudulent', 'requested_by_customer', 'other'])->nullable();

            // Description and notes
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Processing details
            $table->timestamp('processed_at')->nullable();
            $table->string('failure_reason')->nullable();

            // Created by (for audit trail)
            $table->string('created_by_type')->nullable(); // User, Admin, System
            $table->unsignedBigInteger('created_by_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('invoice_id');
            $table->index('stripe_refund_id');
            $table->index('status');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_refunds');
    }
};
