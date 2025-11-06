<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_applied_discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('subscription_id')
                ->constrained('billing_subscriptions')
                ->onDelete('cascade');
            $table->foreignId('discount_id')
                ->constrained('billing_discounts')
                ->onDelete('cascade');

            // Tracking
            $table->timestamp('applied_at');
            $table->timestamp('expires_at')->nullable(); // When this discount stops applying
            $table->integer('total_uses')->default(0); // How many billing cycles it's been applied

            $table->timestamps();

            // Indexes
            $table->index(['subscription_id', 'discount_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_applied_discounts');
    }
};
