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

        // Dimension Set Tree Node (BC Table 481 - for efficient searching)
        Schema::create('dimension_set_tree_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_dimension_set_id')->default(0); // Parent node ID, 0 = root
            $table->foreignId('dimension_value_id')->constrained('dimension_values');
            $table->foreignId('dimension_set_id')->constrained('dimension_sets');
            $table->boolean('in_use')->default(true);
            $table->timestamps();

            // Indexes for tree traversal and lookup
            $table->index(['parent_dimension_set_id', 'dimension_value_id'], 'dim_set_tree_parent_value');
            $table->index('dimension_set_id', 'dim_set_tree_set_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_set_tree_nodes');
    }
};
