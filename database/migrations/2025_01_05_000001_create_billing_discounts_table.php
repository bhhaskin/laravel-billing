<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_discounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Discount identification
            $table->string('code')->nullable()->unique(); // Null for admin-applied discounts
            $table->string('name');
            $table->text('description')->nullable();

            // Discount value
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2); // Percentage (0-100) or fixed amount
            $table->string('currency', 3)->nullable(); // Required for fixed discounts

            // Applicability
            $table->enum('applies_to', ['all', 'specific_plans'])->default('all');
            $table->json('applicable_plan_ids')->nullable(); // Array of plan UUIDs

            // Duration
            $table->enum('duration', ['once', 'repeating', 'forever'])->default('once');
            $table->integer('duration_in_months')->nullable(); // For repeating discounts

            // Usage limits
            $table->integer('max_redemptions')->nullable(); // Null = unlimited
            $table->integer('redemptions_count')->default(0);

            // Validity period
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('code');
            $table->index('is_active');
            $table->index(['starts_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_discounts');
    }
};
