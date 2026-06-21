<?php

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
        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();

            // Parent Order
            $table->foreignId('sales_order_id')
                ->constrained('sales_orders')
                ->onDelete('cascade');

            // Line Number
            $table->integer('line_number');

            // Item Information
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->string('item_code', 20)->nullable();
            $table->string('description');
            $table->string('description_2', 100)->nullable();
            $table->string('variant_code', 20)->nullable();

            // Posting Groups (copied from item)
            $table->foreignId('general_product_posting_group_id')
                ->nullable()
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->nullable()
                ->constrained('inventory_posting_groups');

            // Quantity and UOM
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_shipped', 15, 4)->default(0);
            $table->decimal('quantity_invoiced', 15, 4)->default(0);
            $table->decimal('quantity_to_ship', 15, 4)->default(0);
            $table->string('unit_of_measure_code', 20);
            $table->decimal('qty_per_unit_of_measure', 10, 4)->default(1);
            $table->decimal('quantity_base', 15, 4); // In base UOM

            // Pricing
            $table->decimal('unit_price', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable(); // For margin calc
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 4)->default(0);

            // Amounts
            $table->decimal('line_total', 15, 4); // quantity * unit_price
            $table->decimal('line_amount', 15, 4); // after line discount

            // VAT
            $table->string('vat_code', 20)->nullable();
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0);
            $table->decimal('amount_including_vat', 15, 4)->default(0);

            // Pricing Source (for audit)
            $table->string('price_source', 50)->nullable(); // e.g., 'PRICE_LIST_001', 'ITEM_CARD'
            $table->foreignId('pricing_master_id')->nullable()->constrained('pricing_master');

            // Planning
            $table->date('planned_delivery_date')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->date('promised_delivery_date')->nullable();

            // Reservation (inventory allocation)
            $table->decimal('reserved_quantity', 15, 4)->default(0);
            $table->foreignId('reservation_entry_id')->nullable();

            // Item Tracking
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Location/Bin for picking
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('bin_code', 20)->nullable();

            // Status
            $table->enum('line_status', [
                'OPEN',
                'PARTIALLY_SHIPPED',
                'SHIPPED',
                'INVOICED',
                'CLOSED',
                'CANCELLED',
            ])->default('OPEN');

            // Return/Replacement specific
            $table->unsignedBigInteger('return_against_line_id')->nullable();
            $table->decimal('return_quantity', 15, 4)->default(0);

            // Dimensions
            $table->json('dimensions')->nullable();

            // Comments
            $table->text('comment')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['sales_order_id', 'line_number']);
            $table->index(['item_id', 'line_status']);
            $table->index(['planned_delivery_date', 'line_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
    }
};
