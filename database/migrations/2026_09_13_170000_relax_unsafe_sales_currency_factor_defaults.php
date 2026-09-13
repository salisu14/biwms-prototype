<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove unsafe database-level factor-1 defaults from the sales document tables
 * whose creation paths always supply or safely resolve the factor.
 *
 * currency_factor is LCY per 1 FCY. A database default of 1 silently stamps a
 * foreign document with an authoritative LCY rate. Making these columns
 * nullable with no default removes that fabrication. Application paths for
 * these tables always provide a factor before insert:
 *
 *   - sales_orders              : model default + creating hook resolve 1 for LCY;
 *                                 no raw insert path omits it.
 *   - posted_sales_invoices     : both posting paths set the factor explicitly.
 *   - sales_shipment_headers    : no live creation path writes this table.
 *
 * This migration changes only the column default/nullability; existing row
 * values are not touched.
 *
 * IMPORTANT: only the DATABASE default is removed. Application-level defaults
 * remain and are still unsafe for foreign documents — for example SalesOrder's
 * model default/creating hook resolves 1, and SalesInvoiceService::post() and
 * SalesCreditMemoService::post() hard-code factor 1. Those are pre-existing
 * runtime defects that this schema change does not fix and that require a
 * later bounded correction.
 *
 * NOT relaxed here: posted_sales_credit_memos.currency_factor, because
 * PostedSalesCreditMemo::correct() creates a memo without supplying a factor and
 * still relies on the default. That path requires a bounded correction first.
 */
return new class extends Migration
{
    /**
     * @var array<string, array{0: int, 1: int}>
     */
    private array $relaxed = [
        'sales_orders' => [15, 6],
        'posted_sales_invoices' => [15, 6],
        'sales_shipment_headers' => [18, 6],
    ];

    public function up(): void
    {
        foreach ($this->relaxed as $tableName => [$precision, $scale]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'currency_factor')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($precision, $scale): void {
                $table->decimal('currency_factor', $precision, $scale)->nullable()->default(null)->change();
            });
        }
    }

    /**
     * Rollback restores the legacy DEFAULT 1 but deliberately does NOT re-impose
     * NOT NULL. NULL is a valid post-Phase-3A state (the columns are now
     * intentionally nullable), so restoring NOT NULL could fail on rows that
     * legitimately carry NULL. Historical rows are never rewritten just to make
     * a rollback succeed.
     */
    public function down(): void
    {
        foreach ($this->relaxed as $tableName => [$precision, $scale]) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'currency_factor')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($precision, $scale): void {
                $table->decimal('currency_factor', $precision, $scale)->default(1)->nullable()->change();
            });
        }
    }
};
