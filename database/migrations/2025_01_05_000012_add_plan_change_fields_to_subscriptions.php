<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            // Track plan changes
            $table->foreignId('previous_plan_id')->nullable()->after('workspace_id')
                ->constrained('billing_plans')
                ->nullOnDelete();
            $table->timestamp('plan_changed_at')->nullable()->after('ends_at');

            // Scheduled plan changes
            $table->foreignId('scheduled_plan_id')->nullable()->after('previous_plan_id')
                ->constrained('billing_plans')
                ->nullOnDelete();
            $table->timestamp('plan_change_scheduled_for')->nullable()->after('plan_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('billing_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['previous_plan_id']);
            $table->dropForeign(['scheduled_plan_id']);
            $table->dropColumn([
                'previous_plan_id',
                'plan_changed_at',
                'scheduled_plan_id',
                'plan_change_scheduled_for',
            ]);
        });
    }
};
