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
        Schema::create('production_bom_versions', function (Blueprint $table) {
            $table->id();

            // Relationship to parent BOM
            $table->foreignId('production_bom_id')
                ->constrained('production_boms')
                ->cascadeOnDelete();

            $table->string('version_code');
            $table->string('description')->nullable();

            // Status: CERTIFIED, UNDER_DEVELOPMENT, CLOSED
            $table->string('status')->default('UNDER_DEVELOPMENT');

            // Dates for validity
            $table->date('starting_date')->nullable();
            $table->date('ending_date')->nullable();

            // BOM Meta
            $table->string('unit_of_measure_code')->nullable();
            $table->decimal('quantity_per', 18, 4)->default(1.0000);
            $table->decimal('cost_rollup', 18, 4)->default(0.0000);

            // User Tracking
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Indexes for performance and uniqueness
            $table->unique(['production_bom_id', 'version_code'], 'bom_version_unique');
            $table->index('status');
            $table->index(['starting_date', 'ending_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_bom_versions');
    }
};
