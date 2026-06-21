<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_bom_version_lines')) {
            Schema::create('production_bom_version_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('production_bom_version_id')->constrained('production_bom_versions')->cascadeOnDelete();
                $table->integer('line_number')->default(10000);
                $table->string('type')->default('ITEM'); 
                $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->foreignId('production_bom_id_related')->nullable()->constrained('production_boms')->nullOnDelete();
                $table->string('description')->nullable();
                $table->string('unit_of_measure_code')->nullable();
                $table->decimal('quantity_per', 18, 4)->default(1.0000);
                $table->decimal('scrap_percent', 18, 2)->default(0.00);
                $table->string('routing_link_code')->nullable();
                $table->string('flushing_method')->nullable();
                $table->string('position')->nullable();
                $table->string('position_2')->nullable();
                $table->string('position_3')->nullable();
                $table->integer('lead_time_offset_days')->default(0);
                $table->string('location_code')->nullable();
                $table->string('bin_code')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('routing_version_lines')) {
            Schema::create('routing_version_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('routing_version_id')->constrained('routing_versions')->cascadeOnDelete();
                $table->integer('line_number')->default(10000);
                $table->string('operation_no', 20)->nullable();
                $table->string('description')->nullable();
                $table->foreignId('work_center_id')->nullable()->constrained('work_centers')->nullOnDelete();
                $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers')->nullOnDelete();
                $table->decimal('setup_time', 18, 4)->default(0.0000);
                $table->decimal('run_time', 18, 4)->default(0.0000);
                $table->decimal('wait_time', 18, 4)->default(0.0000);
                $table->decimal('move_time', 18, 4)->default(0.0000);
                $table->decimal('queue_time', 18, 4)->default(0.0000);
                $table->decimal('fixed_scrap_quantity', 18, 4)->default(0.0000);
                $table->string('setup_time_unit')->nullable();
                $table->string('run_time_unit')->nullable();
                $table->decimal('direct_unit_cost', 18, 4)->default(0.0000);
                $table->decimal('indirect_cost_percent', 18, 2)->default(0.00);
                $table->decimal('overhead_rate', 18, 4)->default(0.0000);
                $table->decimal('scrap_factor_percent', 18, 2)->default(0.00);
                $table->string('routing_link_code')->nullable();
                $table->foreignId('subcontractor_id')->nullable()->constrained('vendors')->nullOnDelete();
                $table->decimal('subcontracting_cost', 18, 4)->default(0.0000);
                $table->integer('concurrent_capacities')->default(1);
                $table->decimal('lot_size', 18, 4)->default(1.0000);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_version_lines');
        Schema::dropIfExists('production_bom_version_lines');
    }
};
