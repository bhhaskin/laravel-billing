<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.subscription_items', 'billing_subscription_items'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscription_id')->constrained(config('billing.tables.subscriptions', 'billing_subscriptions'))->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained(config('billing.tables.plans', 'billing_plans'))->cascadeOnDelete();
            $table->string('stripe_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.subscription_items', 'billing_subscription_items'));
    }
};
