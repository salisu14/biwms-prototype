<?php

use App\Enums\CostingMethod;
use App\Enums\ItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('item_number', 20)->unique();
            $table->string('description');
            $table->text('description_2')->nullable();

            // Posting Groups (Critical for P&L)
            $table->foreignId('general_product_posting_group_id')
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->constrained('inventory_posting_groups');
            $table->string('vat_prod_posting_group', 20)->nullable();

            // Item Type
            $table->enum('item_type', array_column(ItemType::cases(), 'value'))
                ->default('INVENTORY');

            // Costing
            $table->enum('costing_method', array_column(CostingMethod::cases(), 'value'))
                ->default('AVERAGE');

            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('standard_cost', 15, 4)->nullable();
            $table->decimal('last_direct_cost', 15, 4)->nullable();

            // Base price (fallback if no price list hit)
            $table->decimal('unit_price', 15, 4)->default(0)->change();

            // Price calculation method
            $table->enum('price_calculation_method', array_column(\App\Enums\PriceCalculationMethod::cases(), 'value'))
                ->default('STANDARD');

            // Pricing-specific fields
            $table->decimal('profit_percent', 5, 2)->nullable()->after('unit_price');
            $table->decimal('last_direct_cost', 15, 4)->nullable()->change();

            // Default price list for this item
            $table->string('default_price_list_code', 20)->nullable();

            // Allow negative pricing (for promotional items)
            $table->boolean('allow_negative_price')->default(false);

            // Pricing
            $table->decimal('unit_price', 15, 4)->default(0);

            // Inventory Control
            $table->decimal('inventory', 15, 4)->default(0); // Current stock
            $table->decimal('reorder_point', 15, 4)->nullable();
            $table->decimal('reorder_quantity', 15, 4)->nullable();

            // Default Location/Bin
            $table->foreignId('location_id')
                ->nullable()
                ->constrained('locations');
            $table->string('bin_code', 20)->nullable();

            // Physical
            $table->string('base_unit_of_measure', 20)->default('PCS');
            $table->decimal('weight', 10, 4)->nullable();
            $table->decimal('volume', 10, 4)->nullable();

            // WMS
            $table->string('shelf_no', 20)->nullable();
            $table->string('item_tracking_code', 20)->nullable();

            $table->boolean('blocked')->default(false);
            $table->boolean('sales_blocked')->default(false);
            $table->boolean('purchasing_blocked')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
