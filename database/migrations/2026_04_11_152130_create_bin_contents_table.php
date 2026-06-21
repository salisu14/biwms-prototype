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
        Schema::create('bin_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bin_id')->constrained('bins')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();

            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();

            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('quantity_base', 15, 4)->default(0);
            $table->string('unit_of_measure_code', 20);

            $table->decimal('picked_quantity', 15, 4)->default(0); // Reserved for picks
            $table->decimal('negative_adj_qty', 15, 4)->default(0); // Posted but not picked

            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['bin_id', 'item_id', 'lot_no', 'serial_no'], 'idx_bin_inventory');
            $table->index(['item_id', 'zone_id', 'bin_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bin_contents');
    }
};
