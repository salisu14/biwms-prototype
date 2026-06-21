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
        Schema::create('purchase_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('item_id')->constrained();
            $table->date('starting_date')->nullable();
            $table->date('ending_date')->nullable();
            $table->decimal('minimum_quantity', 18, 4)->default(0);
            $table->decimal('direct_unit_cost', 18, 4);
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->string('unit_of_measure_code', 10)->nullable();
            $table->string('vendor_item_no', 20)->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'item_id', 'starting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_prices');
    }
};
