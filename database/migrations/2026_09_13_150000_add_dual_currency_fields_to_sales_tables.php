<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the minimum dual-currency representation fields to sales tables.
 *
 * Canonical convention: currency_factor = LCY per 1 FCY, so LCY = FCY x factor.
 * Every new LCY column is NULLABLE and is never backfilled. Existing historical
 * rows are not reinterpreted, and no document amount is copied into an LCY
 * column. Each LCY column mirrors the precision/scale of its source document
 * column so an LCY equivalent cannot be truncated relative to the amount it
 * derives from.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, array{0: string, 1: int, 2: int}>>
     */
    private array $lcyColumns = [
        'sales_orders' => [
            ['subtotal_lcy', 15, 4],
            ['line_discount_total_lcy', 15, 4],
            ['invoice_discount_amount_lcy', 15, 4],
            ['total_amount_lcy', 15, 4],
            ['total_vat_lcy', 15, 4],
            ['grand_total_lcy', 15, 4],
        ],
        'sales_order_lines' => [
            ['unit_price_lcy', 15, 4],
            ['line_total_lcy', 15, 4],
            ['line_amount_lcy', 15, 4],
            ['line_discount_amount_lcy', 15, 4],
            ['vat_amount_lcy', 15, 4],
        ],
        'sales_invoices' => [
            ['total_amount_lcy', 18, 2],
        ],
        'sales_invoice_lines' => [
            ['unit_price_lcy', 18, 2],
            ['line_total_lcy', 18, 2],
            ['discount_amount_lcy', 18, 2],
            ['vat_amount_lcy', 18, 2],
        ],
        'posted_sales_invoices' => [
            ['subtotal_lcy', 15, 4],
            ['line_discount_total_lcy', 15, 4],
            ['invoice_discount_amount_lcy', 15, 4],
            ['total_amount_lcy', 15, 4],
            ['total_vat_lcy', 15, 4],
            ['grand_total_lcy', 15, 4],
            ['remaining_amount_lcy', 15, 4],
        ],
        'posted_sales_invoice_lines' => [
            ['unit_price_lcy', 15, 4],
            ['line_total_lcy', 15, 4],
            ['line_amount_lcy', 15, 4],
            ['line_discount_amount_lcy', 15, 4],
            ['vat_amount_lcy', 15, 4],
        ],
        'sales_credit_memos' => [
            ['total_amount_lcy', 15, 2],
        ],
        'sales_credit_memo_lines' => [
            ['unit_price_lcy', 15, 5],
            ['line_discount_amount_lcy', 15, 2],
            ['vat_amount_lcy', 15, 2],
            ['amount_lcy', 15, 2],
            ['amount_including_vat_lcy', 15, 2],
        ],
        'posted_sales_credit_memos' => [
            ['subtotal_lcy', 15, 4],
            ['total_amount_lcy', 15, 4],
            ['total_vat_lcy', 15, 4],
            ['grand_total_lcy', 15, 4],
            ['remaining_amount_lcy', 15, 4],
        ],
        'posted_sales_credit_memo_lines' => [
            ['unit_price_lcy', 15, 4],
            ['line_total_lcy', 15, 4],
            ['line_amount_lcy', 15, 4],
            ['line_discount_amount_lcy', 15, 4],
            ['vat_amount_lcy', 15, 4],
        ],
        'sales_shipment_lines' => [
            ['unit_price_lcy', 18, 4],
            ['line_amount_lcy', 18, 4],
        ],
    ];

    public function up(): void
    {
        foreach ($this->lcyColumns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as [$column, $precision, $scale]) {
                    if (! Schema::hasColumn($tableName, $column)) {
                        $table->decimal($column, $precision, $scale)->nullable();
                    }
                }
            });
        }

        // Sales invoices currently carry only a currency code. Add an explicit
        // nullable factor and currency reference so a document rate can be
        // persisted, mirroring the posted-document currency conventions.
        if (Schema::hasTable('sales_invoices')) {
            Schema::table('sales_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('sales_invoices', 'currency_factor')) {
                    $table->decimal('currency_factor', 15, 6)->nullable();
                }
                if (! Schema::hasColumn('sales_invoices', 'currency_id')) {
                    $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
                }
            });
        }

        // Draft sales credit memos also lack a rate column.
        if (Schema::hasTable('sales_credit_memos')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                if (! Schema::hasColumn('sales_credit_memos', 'currency_factor')) {
                    $table->decimal('currency_factor', 15, 6)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->lcyColumns as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns): void {
                foreach ($columns as [$column]) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sales_invoices')) {
            Schema::table('sales_invoices', function (Blueprint $table): void {
                if (Schema::hasColumn('sales_invoices', 'currency_id')) {
                    $table->dropConstrainedForeignId('currency_id');
                }
                if (Schema::hasColumn('sales_invoices', 'currency_factor')) {
                    $table->dropColumn('currency_factor');
                }
            });
        }

        if (Schema::hasTable('sales_credit_memos') && Schema::hasColumn('sales_credit_memos', 'currency_factor')) {
            Schema::table('sales_credit_memos', function (Blueprint $table): void {
                $table->dropColumn('currency_factor');
            });
        }
    }
};
