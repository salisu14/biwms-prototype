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
        Schema::create('warehouse_pick_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_pick_id')->constrained('warehouse_picks')->cascadeOnDelete();
            $table->integer('line_no');
            $table->integer('source_line_no')->nullable();
            $table->foreignId('item_id')->constrained('items');
            $table->string('description', 200)->nullable();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('quantity_to_handle', 15, 4)->default(0);
            $table->decimal('quantity_handled', 15, 4)->default(0);
            $table->decimal('quantity_base', 15, 4)->default(0);
            $table->string('unit_of_measure_code', 20)->default('PCS');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->foreignId('destination_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('destination_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('line_status', 30)->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['warehouse_pick_id', 'line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_pick_lines');
    }
};
