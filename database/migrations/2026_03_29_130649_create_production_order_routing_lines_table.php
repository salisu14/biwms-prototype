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
        // Production Order Routing Lines
        Schema::create('production_order_routing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->onDelete('cascade');
            $table->integer('line_number');
            $table->string('operation_no');
            $table->text('description')->nullable();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers');

            $table->decimal('setup_time', 15, 4)->default(0);
            $table->decimal('run_time', 15, 4)->default(0);
            $table->decimal('wait_time', 15, 4)->default(0);
            $table->decimal('move_time', 15, 4)->default(0);
            $table->string('setup_time_unit')->default('MINUTES');
            $table->string('run_time_unit')->default('MINUTES');

            $table->decimal('actual_setup_time', 15, 4)->default(0);
            $table->decimal('actual_run_time', 15, 4)->default(0);

            $table->decimal('expected_output_quantity', 15, 4)->default(0);
            $table->decimal('actual_output_quantity', 15, 4)->default(0);
            $table->decimal('scrap_quantity', 15, 4)->default(0);

            $table->dateTime('starting_date_time')->nullable();
            $table->dateTime('ending_date_time')->nullable();
            $table->dateTime('actual_starting_date_time')->nullable();
            $table->dateTime('actual_ending_date_time')->nullable();

            $table->string('status')->default('PLANNED'); // PLANNED, IN_PROGRESS, COMPLETED

            $table->string('routing_link_code')->nullable();

            $table->decimal('direct_cost', 15, 4)->default(0);
            $table->decimal('overhead_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);

            $table->timestamps();

            $table->index(['production_order_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_routing_lines');
    }
};
