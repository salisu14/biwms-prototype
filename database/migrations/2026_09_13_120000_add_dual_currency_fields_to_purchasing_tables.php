<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the minimum dual-currency representation fields to purchasing tables.
     *
     * Canonical convention: currency_factor / exchange_rate = LCY per 1 FCY,
     * so LCY = FCY x factor. All new LCY columns are nullable so existing
     * historical rows remain valid and are never silently reinterpreted as
     * converted data. Existing NGN-only documents may resolve factor = 1 in
     * the domain layer, but the database must not stamp foreign-currency
     * history with an authoritative factor.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_orders', 'currency_factor')) {
                $table->decimal('currency_factor', 15, 6)->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'total_amount_lcy')) {
                $table->decimal('total_amount_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'total_vat_lcy')) {
                $table->decimal('total_vat_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_orders', 'grand_total_lcy')) {
                $table->decimal('grand_total_lcy', 15, 4)->nullable();
            }
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_order_lines', 'unit_cost_lcy')) {
                $table->decimal('unit_cost_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_order_lines', 'line_total_lcy')) {
                $table->decimal('line_total_lcy', 15, 4)->nullable();
            }
        });

        Schema::table('purchase_receipts', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_receipts', 'exchange_rate')) {
                // Preserve historical NULLs; Phase 2 will explicitly carry
                // the authorized document rate into new foreign-currency receipts.
                $table->decimal('exchange_rate', 19, 6)->nullable()->default(null)->change();
            }
        });

        Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_receipt_lines', 'line_amount_lcy')) {
                $table->decimal('line_amount_lcy', 18, 4)->nullable();
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_invoices', 'total_amount_lcy')) {
                $table->decimal('total_amount_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_invoices', 'total_vat_lcy')) {
                $table->decimal('total_vat_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_invoices', 'grand_total_lcy')) {
                $table->decimal('grand_total_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('purchase_invoices', 'remaining_amount_lcy')) {
                $table->decimal('remaining_amount_lcy', 15, 4)->nullable();
            }
        });

        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_invoice_lines', 'line_total_lcy')) {
                $table->decimal('line_total_lcy', 15, 4)->nullable();
            }
        });

        Schema::table('posted_purchase_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('posted_purchase_invoices', 'total_amount_lcy')) {
                $table->decimal('total_amount_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('posted_purchase_invoices', 'total_vat_lcy')) {
                $table->decimal('total_vat_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('posted_purchase_invoices', 'grand_total_lcy')) {
                $table->decimal('grand_total_lcy', 15, 4)->nullable();
            }
            if (! Schema::hasColumn('posted_purchase_invoices', 'remaining_amount_lcy')) {
                $table->decimal('remaining_amount_lcy', 15, 4)->nullable();
            }
        });

        Schema::table('posted_purchase_invoice_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('posted_purchase_invoice_lines', 'line_total_lcy')) {
                $table->decimal('line_total_lcy', 15, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            foreach (['currency_factor', 'total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy'] as $column) {
                if (Schema::hasColumn('purchase_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            foreach (['unit_cost_lcy', 'line_total_lcy'] as $column) {
                if (Schema::hasColumn('purchase_order_lines', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_receipts', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_receipts', 'exchange_rate')) {
                $table->decimal('exchange_rate', 19, 6)->nullable()->default(null)->change();
            }
        });

        Schema::table('purchase_receipt_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_receipt_lines', 'line_amount_lcy')) {
                $table->dropColumn('line_amount_lcy');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table): void {
            foreach (['total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy', 'remaining_amount_lcy'] as $column) {
                if (Schema::hasColumn('purchase_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('purchase_invoice_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_invoice_lines', 'line_total_lcy')) {
                $table->dropColumn('line_total_lcy');
            }
        });

        Schema::table('posted_purchase_invoices', function (Blueprint $table): void {
            foreach (['total_amount_lcy', 'total_vat_lcy', 'grand_total_lcy', 'remaining_amount_lcy'] as $column) {
                if (Schema::hasColumn('posted_purchase_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('posted_purchase_invoice_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('posted_purchase_invoice_lines', 'line_total_lcy')) {
                $table->dropColumn('line_total_lcy');
            }
        });
    }
};
