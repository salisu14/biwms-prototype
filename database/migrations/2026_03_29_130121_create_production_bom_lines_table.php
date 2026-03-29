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
        // Production BOM Lines
        Schema::create('production_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_bom_id')->constrained('production_boms')->onDelete('cascade');
            $table->integer('line_number');
            $table->string('type'); // ITEM, PRODUCTION_BOM
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->foreignId('production_bom_id_related')->nullable()->constrained('production_boms');
            $table->text('description')->nullable();
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('quantity_per', 15, 4)->default(0);
            $table->decimal('scrap_percent', 5, 2)->default(0);
            $table->string('routing_link_code')->nullable();
            $table->string('flushing_method')->nullable(); // MANUAL, FORWARD, BACKWARD
            $table->string('position')->nullable();
            $table->string('position_2')->nullable();
            $table->string('position_3')->nullable();
            $table->integer('lead_time_offset_days')->default(0);
            $table->string('location_code')->nullable();
            $table->string('bin_code')->nullable();
            $table->timestamps();

            $table->index(['production_bom_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_bom_lines');
    }
};
