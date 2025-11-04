<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.usage_records', 'billing_usage_records'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subscription_item_id')->constrained(config('billing.tables.subscription_items', 'billing_subscription_items'))->cascadeOnDelete();
            $table->integer('quantity');
            $table->string('action')->default('set'); // 'set', 'increment'
            $table->timestamp('timestamp');
            $table->boolean('reported_to_stripe')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_item_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.usage_records', 'billing_usage_records'));
    }
};
