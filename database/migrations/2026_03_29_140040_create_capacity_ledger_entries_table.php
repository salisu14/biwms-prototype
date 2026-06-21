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
        // Capacity Ledger Entries
        Schema::create('capacity_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders');
            $table->foreignId('routing_line_id')->nullable()->constrained('production_order_routing_lines');
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->foreignId('machine_center_id')->nullable()->constrained('machine_centers');
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete();
            $table->foreignId('capex_project_id')->nullable()->constrained('capex_projects')->nullOnDelete();

            $table->date('posting_date');
            $table->string('document_number');

            $table->decimal('setup_time', 15, 4)->default(0);
            $table->decimal('run_time', 15, 4)->default(0);
            $table->decimal('stop_time', 15, 4)->default(0);
            $table->string('setup_time_unit')->default('MINUTES');
            $table->string('run_time_unit')->default('MINUTES');

            $table->decimal('output_quantity', 15, 4)->default(0);
            $table->decimal('scrap_quantity', 15, 4)->default(0);

            $table->decimal('direct_cost', 15, 4)->default(0);
            $table->decimal('overhead_cost', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);

            $table->string('type')->default('RUN'); // SETUP, RUN, STOP, OUTPUT

            $table->timestamps();

            $table->index(['production_order_id', 'posting_date']);
            $table->index(['work_center_id', 'posting_date']);
            $table->index(['document_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capacity_ledger_entries');
    }
};
