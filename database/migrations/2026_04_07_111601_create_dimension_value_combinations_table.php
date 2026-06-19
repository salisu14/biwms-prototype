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
        // Dimension Value Combinations (for Limited type)
        Schema::create('dimension_value_combinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_combination_id')->constrained('dimension_combinations');
            $table->string('dimension_1_value_code', 20);
            $table->string('dimension_2_value_code', 20);
            $table->boolean('blocked')->default(true); // If true, this specific combo is blocked
            $table->timestamps();

            $table->unique(['dimension_combination_id', 'dimension_1_value_code', 'dimension_2_value_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_value_combinations');
    }
};
