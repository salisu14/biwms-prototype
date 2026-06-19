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
        Schema::create('item_uom_assignments', function (Blueprint $table) {
            $table->id('assignment_id');

            $table->foreignId('item_id')->constrained('items')->onDelete('cascade');
            $table->foreignId('uom_id')->constrained('unit_of_measures')->onDelete('cascade');

            // UOM type: base, sales, purchase, inventory, shipping, reporting, etc.
            $table->string('uom_type', 30); // BASE, SALES, PURCHASE, SHIPPING, etc.

            // Conversion factor to base UOM
            $table->decimal('conversion_factor', 15, 6)->default(1.000000);

            // Is this the primary/default for this type?
            $table->boolean('is_default')->default(false);

            // Sort order for display
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Unique constraint: one UOM type per item (or allow multiple with is_default flag)
            $table->unique(['item_id', 'uom_type', 'uom_id']);
            $table->index(['item_id', 'uom_type']);
            $table->index(['uom_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_uom_assignments');
    }
};
