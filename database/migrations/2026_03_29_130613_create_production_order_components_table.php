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
        // Production Order Components
        Schema::create('production_order_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->integer('line_number');
            $table->foreignId('item_id')->constrained('items');
            $table->text('description')->nullable();
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('quantity_per', 15, 4)->default(0);
            $table->decimal('expected_quantity', 15, 4)->default(0);
            $table->decimal('expected_quantity_base', 15, 4)->default(0);
            $table->decimal('actual_quantity_consumed', 15, 4)->default(0);
            $table->decimal('actual_scrap_quantity', 15, 4)->default(0);
            $table->decimal('remaining_quantity', 15, 4)->default(0);
            $table->decimal('scrap_percent', 5, 2)->default(0);
            $table->string('flushing_method')->default('MANUAL');
            $table->string('routing_link_code')->nullable();
            $table->string('location_code')->nullable();
            $table->string('bin_code')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('reserved_quantity', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['production_order_id', 'line_number']);
            $table->index(['item_id', 'production_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_components');
    }
};
