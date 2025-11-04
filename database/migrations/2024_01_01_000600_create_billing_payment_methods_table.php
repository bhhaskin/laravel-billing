<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.payment_methods', 'billing_payment_methods'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained(config('billing.tables.customers', 'billing_customers'))->cascadeOnDelete();
            $table->string('stripe_id')->unique();
            $table->string('type'); // 'card', 'bank_account', etc.
            $table->string('brand')->nullable(); // 'visa', 'mastercard', etc.
            $table->string('last_four', 4)->nullable();
            $table->string('exp_month', 2)->nullable();
            $table->string('exp_year', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('customer_id');
            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.payment_methods', 'billing_payment_methods'));
    }
};
