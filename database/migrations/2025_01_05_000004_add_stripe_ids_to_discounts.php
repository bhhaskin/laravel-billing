<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_discounts', function (Blueprint $table) {
            $table->string('stripe_coupon_id')->nullable()->after('uuid');
            $table->string('stripe_promotion_code_id')->nullable()->after('stripe_coupon_id');

            $table->index('stripe_coupon_id');
            $table->index('stripe_promotion_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('billing_discounts', function (Blueprint $table) {
            $table->dropIndex(['stripe_coupon_id']);
            $table->dropIndex(['stripe_promotion_code_id']);
            $table->dropColumn(['stripe_coupon_id', 'stripe_promotion_code_id']);
        });
    }
};
