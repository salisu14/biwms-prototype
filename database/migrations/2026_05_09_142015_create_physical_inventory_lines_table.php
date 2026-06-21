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
        Schema::create('physical_inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('physical_inventory_journals')->cascadeOnDelete();
            $table->integer('line_no');
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code')->nullable();
            $table->string('location_code')->nullable();
            $table->string('bin_code')->nullable();
            $table->string('shelf_no')->nullable();
            $table->decimal('quantity_base', 18, 4)->default(0)->comment('Qty on hand from system');
            $table->decimal('qty_physical_inventory', 18, 4)->default(0)->comment('Qty actually counted');
            $table->decimal('qty_calculated', 18, 4)->default(0)->comment('Difference qty');
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('qty_per_unit_of_measure', 18, 4)->default(1);
            $table->enum('entry_type', ['Positive Adjmt.', 'Negative Adjmt.'])->nullable();
            $table->decimal('unit_amount', 18, 4)->default(0);
            $table->decimal('amount', 18, 4)->default(0);
            $table->string('item_description')->nullable();
            $table->string('reason_code')->nullable();
            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('lot_no')->nullable();
            $table->date('expiration_date')->nullable();
            $table->string('phys_invt_counting_period_code')->nullable();
            $table->enum('phys_invt_counting_period_type', ['Item', 'SKU'])->nullable();
            $table->date('last_counting_date')->nullable();
            $table->date('next_counting_date')->nullable();
            $table->integer('count_frequency_per_year')->nullable();
            $table->string('inventory_posting_group')->nullable();
            $table->string('gen_bus_posting_group')->nullable();
            $table->string('gen_prod_posting_group')->nullable();
            $table->boolean('use_item_tracking')->default(false);
            $table->decimal('qty_to_handle', 18, 4)->default(0);
            $table->decimal('qty_to_invoice', 18, 4)->default(0);
            $table->decimal('qty_handled', 18, 4)->default(0);
            $table->decimal('qty_invoiced', 18, 4)->default(0);
            $table->integer('no_of_phys_invt_lines')->default(0);
            $table->timestamps();

            $table->unique(['journal_id', 'line_no']);
            $table->index(['item_id', 'location_code', 'bin_code']);
            $table->index(['lot_no', 'serial_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_inventory_lines');
    }
};
