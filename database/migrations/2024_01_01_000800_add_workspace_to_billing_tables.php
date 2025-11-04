<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only run if workspace model is configured
        if (! config('billing.workspace_model')) {
            return;
        }

        Schema::table(config('billing.tables.customers', 'billing_customers'), function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('billable_id');
            $table->index('workspace_id');
        });

        Schema::table(config('billing.tables.subscriptions', 'billing_subscriptions'), function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->after('customer_id');
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        if (! config('billing.workspace_model')) {
            return;
        }

        Schema::table(config('billing.tables.customers', 'billing_customers'), function (Blueprint $table) {
            $table->dropIndex(['workspace_id']);
            $table->dropColumn('workspace_id');
        });

        Schema::table(config('billing.tables.subscriptions', 'billing_subscriptions'), function (Blueprint $table) {
            $table->dropIndex(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
    }
};
