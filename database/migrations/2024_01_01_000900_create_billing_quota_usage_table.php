<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('billing.tables.quota_usage', 'billing_quota_usage'), function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('billable'); // polymorphic relation to User or other billable models (creates index automatically)
            $table->string('quota_key'); // e.g., 'disk_space', 'bandwidth', 'websites'
            $table->decimal('current_usage', 20, 4)->default(0); // current usage amount
            $table->json('warning_thresholds_triggered')->nullable(); // track which warnings were already fired
            $table->timestamp('last_exceeded_at')->nullable(); // when quota was last exceeded
            $table->timestamps();

            // Unique constraint: one record per billable entity per quota key
            $table->unique(['billable_type', 'billable_id', 'quota_key'], 'billable_quota_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('billing.tables.quota_usage', 'billing_quota_usage'));
    }
};
