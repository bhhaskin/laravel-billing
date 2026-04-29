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
 *
 * Idempotency: the migration is safe to re-run if a previous attempt failed
 * partway. Each step checks current schema state and skips work that has
 * already happened.
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
     * SQL CAST target that produces an integer on the current driver.
     * MySQL uses SIGNED; SQLite/PostgreSQL accept INTEGER.
     */
    protected function intCastType(): string
    {
        $driver = Schema::getConnection()->getDriverName();
        return $driver === 'mysql' || $driver === 'mariadb' ? 'SIGNED' : 'INTEGER';
    }

    /**
     * Convert decimal columns to bigInteger cents.
     *
     * Uses an add/copy/drop/rename strategy so it works across MySQL, PostgreSQL,
     * and SQLite without depending on doctrine/dbal. Each step is idempotent so
     * the migration can be re-run after a partial failure.
     */
    protected function convertColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $intCast = $this->intCastType();

        // 1. Add `<col>_cents` columns (skip any that already exist from a partial run).
        $needsAdd = array_filter(
            array_keys($columns),
            fn ($col) => ! Schema::hasColumn($table, "{$col}_cents")
        );

        if (! empty($needsAdd)) {
            Schema::table($table, function (Blueprint $blueprint) use ($needsAdd, $columns) {
                foreach ($needsAdd as $col) {
                    $opts = $columns[$col];
                    $column = $blueprint->bigInteger("{$col}_cents")->nullable();
                    if (array_key_exists('default', $opts)) {
                        $column->default($opts['default']);
                    }
                }
            });
        }

        // 2. Copy data: only run for columns where the original (decimal) still exists.
        foreach (array_keys($columns) as $col) {
            if (Schema::hasColumn($table, $col)) {
                DB::statement("UPDATE {$table} SET {$col}_cents = CAST(ROUND({$col} * 100) AS {$intCast})");
            }
        }

        // 3. Drop original decimal columns that still exist.
        $toDrop = array_filter(
            array_keys($columns),
            fn ($col) => Schema::hasColumn($table, $col)
        );

        if (! empty($toDrop)) {
            Schema::table($table, function (Blueprint $blueprint) use ($toDrop) {
                $blueprint->dropColumn($toDrop);
            });
        }

        // 4. Rename `<col>_cents` → `<col>` for any that haven't been renamed yet.
        foreach (array_keys($columns) as $col) {
            if (! Schema::hasColumn($table, $col) && Schema::hasColumn($table, "{$col}_cents")) {
                Schema::table($table, function (Blueprint $blueprint) use ($col) {
                    $blueprint->renameColumn("{$col}_cents", $col);
                });
            }
        }
    }

    /**
     * Reverse the cents conversion, restoring decimal(10,2) columns.
     */
    protected function revertColumns(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $needsAdd = array_filter(
            array_keys($columns),
            fn ($col) => ! Schema::hasColumn($table, "{$col}_decimal")
        );

        if (! empty($needsAdd)) {
            Schema::table($table, function (Blueprint $blueprint) use ($needsAdd, $columns) {
                foreach ($needsAdd as $col) {
                    $opts = $columns[$col];
                    $column = $blueprint->decimal("{$col}_decimal", 10, 2)->nullable();
                    if (array_key_exists('default', $opts)) {
                        $column->default($opts['default']);
                    }
                }
            });
        }

        foreach (array_keys($columns) as $col) {
            if (Schema::hasColumn($table, $col)) {
                DB::statement("UPDATE {$table} SET {$col}_decimal = {$col} / 100.0");
            }
        }

        $toDrop = array_filter(
            array_keys($columns),
            fn ($col) => Schema::hasColumn($table, $col)
        );

        if (! empty($toDrop)) {
            Schema::table($table, function (Blueprint $blueprint) use ($toDrop) {
                $blueprint->dropColumn($toDrop);
            });
        }

        foreach (array_keys($columns) as $col) {
            if (! Schema::hasColumn($table, $col) && Schema::hasColumn($table, "{$col}_decimal")) {
                Schema::table($table, function (Blueprint $blueprint) use ($col) {
                    $blueprint->renameColumn("{$col}_decimal", $col);
                });
            }
        }
    }

    protected function splitDiscountValue(): void
    {
        $table = 'billing_discounts';

        if (! Schema::hasTable($table)) {
            return;
        }

        $intCast = $this->intCastType();

        if (! Schema::hasColumn($table, 'percentage')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('percentage', 5, 2)->nullable()->after('type');
            });
        }

        if (! Schema::hasColumn($table, 'amount_cents')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->bigInteger('amount_cents')->nullable()->after('percentage');
            });
        }

        if (Schema::hasColumn($table, 'value')) {
            DB::statement("UPDATE {$table} SET percentage = value WHERE type = 'percentage'");
            DB::statement("UPDATE {$table} SET amount_cents = CAST(ROUND(value * 100) AS {$intCast}) WHERE type = 'fixed'");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('value');
            });
        }
    }

    protected function mergeDiscountValue(): void
    {
        $table = 'billing_discounts';

        if (! Schema::hasTable($table)) {
            return;
        }

        if (! Schema::hasColumn($table, 'value')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->decimal('value', 10, 2)->nullable()->after('type');
            });
        }

        if (Schema::hasColumn($table, 'percentage')) {
            DB::statement("UPDATE {$table} SET value = percentage WHERE type = 'percentage'");
        }

        if (Schema::hasColumn($table, 'amount_cents')) {
            DB::statement("UPDATE {$table} SET value = amount_cents / 100.0 WHERE type = 'fixed'");
        }

        $toDrop = array_filter(
            ['percentage', 'amount_cents'],
            fn ($col) => Schema::hasColumn($table, $col)
        );

        if (! empty($toDrop)) {
            Schema::table($table, function (Blueprint $blueprint) use ($toDrop) {
                $blueprint->dropColumn($toDrop);
            });
        }
    }
};
