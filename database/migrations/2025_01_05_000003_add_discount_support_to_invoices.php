<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add discount field to invoices
        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->after('subtotal');
        });

        // Add discount tracking fields to invoice items
        Schema::table('billing_invoice_items', function (Blueprint $table) {
            $table->boolean('is_discount')->default(false)->after('is_proration');
            $table->foreignId('discount_id')->nullable()->after('is_discount')
                ->constrained('billing_discounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('billing_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
            $table->dropColumn(['is_discount', 'discount_id']);
        });

        Schema::table('billing_invoices', function (Blueprint $table) {
            $table->dropColumn('discount');
        });
    }
};
