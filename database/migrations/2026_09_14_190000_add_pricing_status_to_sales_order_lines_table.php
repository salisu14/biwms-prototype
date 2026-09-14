<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable Sales line pricing state.
 *
 * Additive and forward-only: `pricing_status` distinguishes a resolved price
 * from an explicitly manual price and from an unresolved foreign-currency price
 * that still requires user action. Existing rows are left NULL (historical /
 * unknown) and are never reinterpreted as resolved or manual without evidence.
 *
 * `price_record_id` carries the source record id for a `sales_prices`-sourced
 * line (the existing `pricing_master_id` remains specific to legacy price
 * lists and is not reused for a different source).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return;
        }

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales_order_lines', 'pricing_status')) {
                $table->string('pricing_status', 20)->nullable()->after('price_source');
            }

            if (! Schema::hasColumn('sales_order_lines', 'price_record_id')) {
                $table->unsignedBigInteger('price_record_id')->nullable()->after('pricing_status');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_order_lines')) {
            return;
        }

        Schema::table('sales_order_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_order_lines', 'price_record_id')) {
                $table->dropColumn('price_record_id');
            }

            if (Schema::hasColumn('sales_order_lines', 'pricing_status')) {
                $table->dropColumn('pricing_status');
            }
        });
    }
};
