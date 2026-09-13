<?php

declare(strict_types=1);

use App\Enums\SalesPriceSource;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Currency-tagged sales price provenance.
 *
 * Existing structures cannot safely represent a negotiated foreign-currency
 * price: customer_price_overrides has no currency column (one row per
 * customer+item) and pricing_master's currency is pinned to USD by its lookup.
 * A dedicated table lets a negotiated USD price coexist with an LCY reference
 * price without one overwriting the other.
 *
 * `currency_code` is explicit and NOT NULL with no default: a price must always
 * declare its own currency. No FX conversion is stored or implied here.
 * Historical pricing tables are neither migrated nor backfilled.
 *
 * FK deletes preserve scope: item/customer/customer-group deletes remove the
 * scoped prices that belong to the deleted owner. Nulling a scope key instead
 * would silently widen a customer-specific price into a group/general price,
 * destroying the information that it was ever customer-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_prices')) {
            return;
        }

        Schema::create('sales_prices', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // Scope: customer-specific, customer-group, or general.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->cascadeOnDelete();

            // Unit of measure is a code, matching existing sales line conventions.
            $table->string('unit_of_measure_code', 20)->nullable();

            // The price is stored in its own declared currency. No default.
            // varchar(3), not char(3): character(3) blank-pads and would break
            // exact equality against a normalized code.
            $table->string('currency_code', 3);
            $table->decimal('price', 18, 4);
            $table->string('source', 20)->default(SalesPriceSource::NEGOTIATED->value);

            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['item_id', 'currency_code', 'is_active'], 'sales_prices_item_currency_active_index');
            $table->index(['customer_id', 'item_id', 'currency_code'], 'sales_prices_customer_item_currency_index');
            $table->index(['customer_group_id', 'item_id', 'currency_code'], 'sales_prices_group_item_currency_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_prices');
    }
};
