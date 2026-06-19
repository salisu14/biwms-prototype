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
        // Machine Centers
        Schema::create('machine_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('work_center_id')->constrained('work_centers');

            $table->decimal('capacity', 15, 4)->default(0);
            $table->decimal('efficiency', 5, 2)->default(100);

            $table->decimal('direct_unit_cost', 15, 4)->default(0);
            $table->decimal('indirect_cost_percent', 5, 2)->default(0);
            $table->decimal('overhead_rate', 15, 4)->default(0);

            $table->decimal('setup_time', 15, 4)->default(0);
            $table->decimal('wait_time', 15, 4)->default(0);
            $table->decimal('move_time', 15, 4)->default(0);

            $table->string('location_code')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machine_centers');
    }
};
