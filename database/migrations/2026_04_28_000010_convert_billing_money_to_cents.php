<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert all monetary columns from decimal(10,2) (dollars) to bigInteger (cents).
 *
 * After this migration, every monetary value in the database is stored as an
 * integer number of minor units (cents for USD, etc.). This eliminates float
 * precision bugs in proration, refund, and credit arithmetic.
 *
 * For Discount, the dual-purpose `value` column is split into `percentage`
 * (decimal 5,2 nullable, range 0-100) and `amount_cents` (bigInteger nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->convertColumns(config('billing.tables.invoices', 'billing_invoices'), [
            'subtotal' => ['default' => 0],
            'tax' => ['default' => 0],
            'total' => ['default' => 0],
            'discount' => ['default' => 0],
        ]);

        $this->convertColumns(config('billing.tables.invoice_items', 'billing_invoice_items'), [
            'unit_price' => [],
            'amount' => [],
        ]);

        $this->convertColumns(config('billing.tables.plans', 'billing_plans'), [
            'price' => [],
        ]);

        $this->convertColumns('billing_refunds', [
            'amount' => [],
        ]);

        $this->convertColumns('billing_customer_credits', [
            'amount' => [],
            'balance_before' => ['default' => 0],
            'balance_after' => ['default' => 0],
        ]);

        $this->convertColumns(config('billing.tables.customers', 'billing_customers'), [
            'credit_balance' => ['default' => 0],
        ]);

        $this->splitDiscountValue();
    }

    public function down(): void
    {
        $this->mergeDiscountValue();

        $this->revertColumns(config('billing.tables.customers', 'billing_customers'), [
            'credit_balance' => ['default' => 0],
        ]);

        $this->revertColumns('billing_customer_credits', [
            'amount' => [],
            'balance_before' => ['default' => 0],
            'balance_after' => ['default' => 0],
        ]);

        $this->revertColumns('billing_refunds', [
            'amount' => [],
        ]);

        $this->revertColumns(config('billing.tables.plans', 'billing_plans'), [
            'price' => [],
        ]);

        $this->revertColumns(config('billing.tables.invoice_items', 'billing_invoice_items'), [
            'unit_price' => [],
            'amount' => [],
        ]);

        $this->revertColumns(config('billing.tables.invoices', 'billing_invoices'), [
            'subtotal' => ['default' => 0],
            'tax' => ['default' => 0],
            'total' => ['default' => 0],
            'discount' => ['default' => 0],
        ]);
    }

    /**
     * Convert decimal columns to bigInteger cents.
     *
     * Uses an add/copy/drop/rename strategy so it works across MySQL, PostgreSQL,
     * and SQLite without depending on doctrine/dbal.
     */
    protected function convertColumns(string $table, array $columns): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            foreach ($columns as $col => $opts) {
                $column = $blueprint->bigInteger("{$col}_cents")->nullable();
                if (array_key_exists('default', $opts)) {
                    $column->default($opts['default']);
                }
            }
        });

        foreach (array_keys($columns) as $col) {
            DB::statement("UPDATE {$table} SET {$col}_cents = CAST(ROUND({$col} * 100) AS INTEGER)");
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->dropColumn(array_keys($columns));
        });

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            foreach (array_keys($columns) as $col) {
                $blueprint->renameColumn("{$col}_cents", $col);
            }
        });
    }

    /**
     * Reverse the cents conversion, restoring decimal(10,2) columns.
     */
    protected function revertColumns(string $table, array $columns): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            foreach ($columns as $col => $opts) {
                $column = $blueprint->decimal("{$col}_decimal", 10, 2)->nullable();
                if (array_key_exists('default', $opts)) {
                    $column->default($opts['default']);
                }
            }
        });

        foreach (array_keys($columns) as $col) {
            DB::statement("UPDATE {$table} SET {$col}_decimal = {$col} / 100.0");
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            $blueprint->dropColumn(array_keys($columns));
        });

        Schema::table($table, function (Blueprint $blueprint) use ($columns) {
            foreach (array_keys($columns) as $col) {
                $blueprint->renameColumn("{$col}_decimal", $col);
            }
        });
    }

    protected function splitDiscountValue(): void
    {
        $table = 'billing_discounts';

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->decimal('percentage', 5, 2)->nullable()->after('type');
            $blueprint->bigInteger('amount_cents')->nullable()->after('percentage');
        });

        DB::statement("UPDATE {$table} SET percentage = value WHERE type = 'percentage'");
        DB::statement("UPDATE {$table} SET amount_cents = CAST(ROUND(value * 100) AS INTEGER) WHERE type = 'fixed'");

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('value');
        });
    }

    protected function mergeDiscountValue(): void
    {
        $table = 'billing_discounts';

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->decimal('value', 10, 2)->nullable()->after('type');
        });

        DB::statement("UPDATE {$table} SET value = percentage WHERE type = 'percentage'");
        DB::statement("UPDATE {$table} SET value = amount_cents / 100.0 WHERE type = 'fixed'");

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn(['percentage', 'amount_cents']);
        });
    }
};
