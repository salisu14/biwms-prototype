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
        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();

            // Foreign key to production order
            $table->foreignId('production_order_id')
                ->constrained('production_orders')
                ->onDelete('cascade');

            // Line identification
            $table->unsignedInteger('line_number');

            // Item information
            $table->foreignId('item_id')->constrained('items');
            $table->string('variant_code', 50)->nullable();
            $table->string('description', 255)->nullable();

            // Quantities
            $table->decimal('quantity', 18, 4);
            $table->string('unit_of_measure_code', 50);
            $table->decimal('quantity_base', 18, 4);

            // Dates
            $table->date('due_date')->nullable();
            $table->dateTime('starting_date_time')->nullable();
            $table->dateTime('ending_date_time')->nullable();

            // References
            $table->foreignId('production_bom_id')->nullable()->constrained('production_boms');
            $table->foreignId('routing_id')->nullable()->constrained('routings');

            // Location
            $table->string('location_code', 50)->nullable();
            $table->string('bin_code', 50)->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_id')->nullable();

            // Costing
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('cost_amount', 18, 4)->nullable();

            // Status
            $table->boolean('finished')->default(false);
            $table->dateTime('finished_at')->nullable();

            // Tracking
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('last_modified_by')->nullable();

            $table->timestamps();

            // Indexes
            $table->unique(['production_order_id', 'line_number']);
            $table->index('item_id');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_lines');
    }
};
