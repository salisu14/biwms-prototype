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
        Schema::create('warehouse_requests', function (Blueprint $table) {
            $table->id();
            $table->string('source_document', 50); // production_order, sales_order, transfer_order
            $table->string('source_no', 50);
            $table->integer('source_line_no');
            $table->foreignId('source_id')->nullable(); // polymorphic

            $table->enum('request_type', ['pick', 'put_away', 'movement']);
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('bin_id')->nullable()->constrained('bins')->nullOnDelete();

            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity', 15, 4);
            $table->decimal('quantity_base', 15, 4);
            $table->string('unit_of_measure_code', 20);
            $table->decimal('quantity_outstanding', 15, 4);

            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->date('expiration_date')->nullable();

            $table->enum('status', ['open', 'partial', 'completed', 'cancelled'])->default('open');
            $table->foreignId('warehouse_activity_id')->nullable()->constrained('warehouse_activities')->nullOnDelete();

            $table->timestamps();

            $table->index(['source_document', 'source_no', 'source_line_no']);
            $table->index(['status', 'location_id', 'item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_requests');
    }
};
