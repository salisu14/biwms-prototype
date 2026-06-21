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
        Schema::create('routing_versions', function (Blueprint $blueprint) {
            $blueprint->id();

            // Foreign Key to parent Routing
            $blueprint->foreignId('routing_id')
                ->constrained('routings')
                ->cascadeOnDelete();

            // Version Metadata
            $blueprint->string('version_code', 20);
            $blueprint->string('description')->nullable();

            // Status: CERTIFIED, UNDER_DEVELOPMENT, CLOSED
            $blueprint->string('status')->default('UNDER_DEVELOPMENT');

            // Type: SERIAL, PARALLEL
            $blueprint->string('type')->default('SERIAL');

            // Dates and Validity
            $blueprint->date('starting_date')->nullable();
            $blueprint->date('ending_date')->nullable();

            // Financials
            $blueprint->decimal('cost_rollup', 18, 4)->default(0.0000);

            // Audit Fields
            $blueprint->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $blueprint->foreignId('last_modified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $blueprint->timestamps();

            // Indexes for performance
            $blueprint->index(['routing_id', 'status']);
            $blueprint->index(['starting_date', 'ending_date']);

            // Ensure version codes are unique per routing
            $blueprint->unique(['routing_id', 'version_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_versions');
    }
};
