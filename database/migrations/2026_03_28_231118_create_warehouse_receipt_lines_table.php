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
        Schema::create('warehouse_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_receipt_id')
                ->constrained('warehouse_receipts')
                ->onDelete('cascade');

            $table->integer('line_number');

            // Item
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code', 20)->nullable();
            $table->string('description')->nullable();

            // Quantities
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_received', 15, 4)->default(0);
            $table->decimal('quantity_outstanding', 15, 4)->default(0);

            // Unit of Measure
            $table->string('unit_of_measure_code', 20);
            $table->decimal('qty_per_unit_of_measure', 10, 4)->default(1);

            // Bin (for directed put-away)
            $table->string('zone_code', 20)->nullable();
            $table->string('bin_code', 20)->nullable();

            // Tracking
            $table->string('serial_number', 50)->nullable();
            $table->string('lot_number', 50)->nullable();
            $table->date('expiration_date')->nullable();

            // Source Line Reference
            $table->unsignedBigInteger('source_line_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_receipt_lines');
    }
};
