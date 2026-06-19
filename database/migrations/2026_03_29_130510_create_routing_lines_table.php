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
        // Routing Lines
        Schema::create('routing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained('routings')->onDelete('cascade');
            $table->integer('line_number');
            $table->string('operation_no');
            $table->text('description')->nullable();
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers');

            $table->decimal('setup_time', 15, 4)->default(0);
            $table->decimal('run_time', 15, 4)->default(0);
            $table->decimal('wait_time', 15, 4)->default(0);
            $table->decimal('move_time', 15, 4)->default(0);
            $table->decimal('queue_time', 15, 4)->default(0);
            $table->decimal('fixed_scrap_quantity', 15, 4)->default(0);

            $table->string('setup_time_unit')->default('MINUTES');
            $table->string('run_time_unit')->default('MINUTES');

            $table->decimal('direct_unit_cost', 15, 4)->default(0);
            $table->decimal('indirect_cost_percent', 5, 2)->default(0);
            $table->decimal('overhead_rate', 15, 4)->default(0);
            $table->decimal('scrap_factor_percent', 5, 2)->default(0);

            $table->string('routing_link_code')->nullable();
            $table->foreignId('subcontractor_id')->nullable()->constrained('vendors');
            $table->decimal('subcontracting_cost', 15, 4)->default(0);
            $table->integer('concurrent_capacities')->default(1);
            $table->decimal('lot_size', 15, 4)->default(0);

            $table->timestamps();

            $table->index(['routing_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_lines');
    }
};
