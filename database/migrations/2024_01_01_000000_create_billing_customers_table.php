<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.customers', 'billing_customers'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('billable'); // polymorphic relation to User or other billable models
            $table->string('stripe_id')->unique()->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.customers', 'billing_customers'));
    }
};
