<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customer_credits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('customer_id')
                ->constrained('billing_customers')
                ->onDelete('cascade');

            // Credit details
            $table->enum('type', ['refund', 'promotional', 'manual_adjustment', 'invoice_payment', 'other'])
                ->default('manual_adjustment');
            $table->decimal('amount', 10, 2); // Positive for credits, negative for debits
            $table->string('currency', 3);
            $table->decimal('balance_before', 10, 2)->default(0);
            $table->decimal('balance_after', 10, 2)->default(0);

            // Description and metadata
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Related records
            $table->foreignId('invoice_id')->nullable()
                ->constrained('billing_invoices')
                ->onDelete('set null');
            $table->foreignId('refund_id')->nullable()
                ->constrained('billing_refunds')
                ->onDelete('set null');
            $table->string('reference_type')->nullable(); // Polymorphic for other related models
            $table->unsignedBigInteger('reference_id')->nullable();

            // Expiration
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            // Created by (for audit trail)
            $table->string('created_by_type')->nullable(); // User, System, Admin
            $table->unsignedBigInteger('created_by_id')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('customer_id');
            $table->index('type');
            $table->index('expires_at');
            $table->index(['reference_type', 'reference_id']);
        });

        // Add balance field to customers table
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->decimal('credit_balance', 10, 2)->default(0)->after('stripe_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });

        Schema::dropIfExists('billing_customer_credits');
    }
};
