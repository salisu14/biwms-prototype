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
        // Work Centers
        Schema::create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('work_center_group_id')->nullable()->constrained('work_center_groups');

            $table->string('unit_of_measure_code')->default('MINUTES');
            $table->decimal('capacity', 15, 4)->default(0);
            $table->decimal('efficiency', 5, 2)->default(100);
            $table->decimal('maximum_efficiency', 5, 2)->default(100);
            $table->decimal('minimum_efficiency', 5, 2)->default(0);

            $table->decimal('direct_unit_cost', 18, 4)->default(0);
            $table->decimal('indirect_cost_percent', 5, 2)->default(0);
            $table->decimal('overhead_rate', 18, 4)->default(0);

            $table->decimal('queue_time', 18, 4)->default(0);
            $table->string('queue_time_unit')->default('MINUTES');

            $table->string('location_code')->nullable();
            $table->string('work_center_account_no')->nullable();
            $table->foreignId('subcontractor_id')->nullable()->constrained('vendors');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_centers');
    }
};
