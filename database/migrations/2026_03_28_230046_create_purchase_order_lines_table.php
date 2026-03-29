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
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->onDelete('cascade');
            $table->integer('line_number');

            // Item info (denormalized for history)
            $table->foreignId('item_id')->constrained('item_masters');
            $table->string('item_code', 50);
            $table->string('description', 255);

            // Quantities and costs
            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure', 20);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('line_total', 15, 4)->default(0);

            // VAT
            $table->string('vat_code', 20)->nullable();
            $table->decimal('vat_percentage', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 4)->default(0);
            $table->decimal('total_amount', 15, 4)->default(0);

            // Tracking
            $table->decimal('received_quantity', 15, 4)->default(0);
            $table->decimal('returned_quantity', 15, 4)->default(0);
            $table->decimal('invoiced_quantity', 15, 4)->default(0);

            $table->date('expected_delivery_date')->nullable();
            $table->text('comment')->nullable();

            $table->foreignId('general_product_posting_group_id')
                ->nullable()
                ->after('item_id')
                ->constrained('general_product_posting_groups');

            $table->string('variant_code', 20)->nullable()->after('item_code');

            $table->timestamps();

            // Indexes
            $table->unique(['purchase_order_id', 'line_number']);
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
