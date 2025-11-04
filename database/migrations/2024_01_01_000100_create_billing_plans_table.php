<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.plans', 'billing_plans'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('interval'); // 'monthly', 'yearly'
            $table->integer('interval_count')->default(1);
            $table->string('type')->default('plan'); // 'plan', 'addon'
            $table->boolean('requires_plan')->default(false); // true for add-ons that need a base plan
            $table->boolean('is_active')->default(true);
            $table->integer('trial_period_days')->default(0);
            $table->integer('grace_period_days')->default(0);
            $table->string('cancellation_behavior')->default('end_of_period'); // 'end_of_period', 'immediate'
            $table->string('change_behavior')->default('immediate'); // 'immediate', 'end_of_period'
            $table->boolean('prorate_changes')->default(true);
            $table->boolean('prorate_cancellations')->default(false);
            $table->json('features')->nullable(); // Array of features included
            $table->json('limits')->nullable(); // Array of quota limits (e.g., ['domains' => 10, 'storage_gb' => 100])
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('interval');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.plans', 'billing_plans'));
    }
};
