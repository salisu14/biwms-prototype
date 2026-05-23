<?php

use App\Enums\CostingMethod;
use App\Enums\InventoryMethod;
use App\Enums\ItemType;
use App\Enums\PriceCalculationMethod;
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
            $table->string('item_code', 20)->unique();
            $table->string('description', 255);
            $table->text('description_2')->nullable();

            // Item Type & Management
            $table->enum('item_type', array_column(ItemType::cases(), 'value'))
                ->default('INVENTORY');
            $table->enum('inventory_method', array_column(InventoryMethod::cases(), 'value'))
                ->default('FIFO');

            // Posting Groups
            $table->foreignId('general_product_posting_group_id')
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->constrained('inventory_posting_groups');
            $table->string('vat_prod_posting_group', 20)->nullable();

            // Core Foreign Keys (Nullable if linked later)
            $table->foreignId('uom_id')->nullable()->constrained('unit_of_measures');
            $table->unsignedBigInteger('sku_id')->nullable()->index(); // Default SKU, FK added in ItemSku migration
            $table->foreignId('vat_id')->nullable()->constrained('vat_masters');
            $table->foreignId('general_posting_setup_id')->nullable()->constrained('general_posting_setups');
            $table->foreignId('inventory_posting_setup_id')->nullable()->constrained('inventory_posting_setups');

            $table->foreignId('vat_product_posting_group_id')
                ->nullable()
                ->constrained('vat_product_posting_groups');

            // Pricing & Costing
            $table->enum('costing_method', array_column(CostingMethod::cases(), 'value'))
                ->default('AVERAGE');
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('standard_cost', 15, 4)->default(0);
            $table->decimal('last_direct_cost', 15, 4)->nullable();
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('profit_percent', 5, 2)->nullable();

            // Pricing Calculation
            $table->enum('price_calculation_method', array_column(PriceCalculationMethod::cases(), 'value'))
                ->default('STANDARD');
            $table->string('default_price_list_code', 20)->nullable();
            $table->boolean('allow_negative_price')->default(false);

            // Inventory Control
            $table->decimal('inventory', 15, 4)->default(0);
            $table->decimal('reorder_point', 15, 4)->nullable();
            $table->decimal('reorder_quantity', 15, 4)->nullable();

            // Default Warehouse Positioning
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('bin_code', 20)->nullable();

            // Physical & WMS Attributes
            $table->foreignId('base_uom_id')->nullable()->constrained('unit_of_measures');

            $table->decimal('weight', 10, 4)->nullable();
            $table->decimal('volume', 10, 4)->nullable();
            $table->string('shelf_no', 20)->nullable();
            $table->string('item_tracking_code', 20)->nullable();
            $table->integer('shelf_life_days')->nullable();

            // Status & Blocks
            $table->boolean('is_active')->default(true);
            $table->boolean('blocked')->default(false);
            $table->boolean('sales_blocked')->default(false);
            $table->boolean('purchasing_blocked')->default(false);

            $table->timestamps();
            $table->softDeletes();
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
