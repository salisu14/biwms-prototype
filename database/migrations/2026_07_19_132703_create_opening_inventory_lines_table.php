<?php

declare(strict_types=1);

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
        Schema::create('opening_inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_inventory_id')->constrained('opening_inventories')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('unit_of_measures');
            $table->decimal('quantity', 24, 8);
            $table->decimal('quantity_base', 24, 8);
            $table->decimal('unit_cost', 24, 8);
            $table->decimal('amount', 24, 4);
            $table->unsignedInteger('line_number');
            $table->string('lot_number', 50)->nullable();
            $table->string('serial_number', 50)->nullable();
            $table->foreignId('item_ledger_entry_id')->nullable()->constrained('item_ledger_entries');
            $table->timestamps();

            $table->unique(['opening_inventory_id', 'line_number']);
            $table->index(['item_id', 'location_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_inventory_lines');
    }
};
