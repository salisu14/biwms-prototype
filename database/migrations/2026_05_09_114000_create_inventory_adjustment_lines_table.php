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
        Schema::create('inventory_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('inventory_adjustment_journals')->cascadeOnDelete();
            $table->integer('line_no');
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code')->nullable();
            $table->string('location_code')->nullable();
            $table->string('bin_code')->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('quantity_base', 18, 4)->default(0);
            $table->decimal('qty_per_unit_of_measure', 18, 4)->default(1);
            $table->enum('entry_type', ['Positive Adjmt.', 'Negative Adjmt.'])->default('Positive Adjmt.');
            $table->string('reason_code')->nullable();
            $table->string('description')->nullable();
            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();
            $table->unsignedBigInteger('dimension_set_id')->nullable();
            $table->unsignedBigInteger('applies_to_entry')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('lot_no')->nullable();
            $table->date('expiration_date')->nullable();
            $table->decimal('line_amount', 18, 4)->default(0);
            $table->decimal('line_discount_amount', 18, 4)->default(0);
            $table->string('inventory_posting_group')->nullable();
            $table->string('gen_bus_posting_group')->nullable();
            $table->string('gen_prod_posting_group')->nullable();
            $table->decimal('quantity_to_handle', 18, 4)->default(0);
            $table->decimal('quantity_to_invoice', 18, 4)->default(0);
            $table->decimal('qty_handled', 18, 4)->default(0);
            $table->decimal('qty_invoiced', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['journal_id', 'line_no']);
            $table->index(['item_id', 'location_code']);
            $table->index(['lot_no', 'serial_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
    }
};
