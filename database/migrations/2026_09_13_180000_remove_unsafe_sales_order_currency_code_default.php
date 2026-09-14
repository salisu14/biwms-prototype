<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the unsafe USD database default from sales_orders.currency_code.
 *
 * Phase 3A retained `DEFAULT 'USD'` on the stated grounds that the
 * unreferenced ConvertQuoteToOrderAction created orders without a currency.
 * That action is dead code, and every live creation path now supplies a
 * validated currency context (the SalesOrder model resolves it on create;
 * blanket and quote conversion resolve before insert). Keeping the default
 * means a raw/query-builder insert that omits currency_code silently becomes a
 * USD document — exactly the fabrication this phase removes.
 *
 * currency_code stays NOT NULL: every legitimate path supplies a value. Only
 * the unsafe default is dropped. Existing rows are not touched (no backfill).
 *
 * This migration is intentionally separate from the Phase 3A migrations, which
 * are never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_orders') || ! Schema::hasColumn('sales_orders', 'currency_code')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->string('currency_code', 3)->nullable(false)->default(null)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_orders') || ! Schema::hasColumn('sales_orders', 'currency_code')) {
            return;
        }

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->string('currency_code', 3)->nullable(false)->default('USD')->change();
        });
    }
};
