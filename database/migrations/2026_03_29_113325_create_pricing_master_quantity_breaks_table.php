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
        Schema::create('pricing_master_quantity_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pricing_master_id')
                ->constrained('pricing_master')
                ->onDelete('cascade');

            $table->decimal('minimum_quantity', 15, 4);
            $table->decimal('maximum_quantity', 15, 4)->nullable(); // NULL = no upper limit

            // Price at this tier
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 15, 4)->nullable();

            // Optional: Different UOM at this tier (e.g., pallet pricing)
            $table->string('unit_of_measure_code', 20)->nullable();

            $table->integer('line_number'); // For ordering tiers
            $table->timestamps();

            $table->unique(['pricing_master_id', 'minimum_quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_master_quantity_breaks');
    }
};
