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
        Schema::create('warehouse_activity_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_activity_id')->constrained('warehouse_activities')->cascadeOnDelete();
            $table->integer('line_no');

            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity_to_handle', 15, 4);
            $table->decimal('quantity_handled', 15, 4)->default(0);
            $table->decimal('quantity_base', 15, 4);
            $table->string('unit_of_measure_code', 20);

            // Source (Take From)
            $table->foreignId('source_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('source_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('source_lot_no', 50)->nullable();
            $table->string('source_serial_no', 50)->nullable();

            // Destination (Place To)
            $table->foreignId('destination_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('destination_bin_id')->nullable()->constrained('bins')->nullOnDelete();
            $table->string('destination_lot_no', 50)->nullable();
            $table->string('destination_serial_no', 50)->nullable();

            // Breakbulk (split from larger unit)
            $table->boolean('breakbulk')->default(false);
            $table->decimal('breakbulk_quantity', 15, 4)->nullable();

            // Serial/Lot tracking
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();
            $table->date('warranty_date')->nullable();

            $table->enum('line_status', ['open', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['warehouse_activity_id', 'line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_activity_lines');
    }
};
