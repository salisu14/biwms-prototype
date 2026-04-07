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

        // Dimension Sets (BC Table 480 header concept)
        Schema::create('dimension_sets', function (Blueprint $table) {
            $table->id(); // BC: "Dimension Set ID"
            $table->string('description', 250)->nullable();
            $table->string('dimension_hash', 32)->unique()->nullable(); // MD5 for quick lookup
            $table->timestamps();
        });


        Schema::create('dimension_combinations', function (Blueprint $table) {
            $table->id();
            $table->string('dimension_1_code', 20);
            $table->string('dimension_2_code', 20);
            $table->enum('combination_type', ['no_limitation', 'limited', 'blocked'])->default('no_limitation');
            $table->timestamps();

            $table->unique(['dimension_1_code', 'dimension_2_code']);
            $table->index(['dimension_1_code', 'dimension_2_code', 'combination_type']);
        });

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
        Schema::dropIfExists('dimension_sets');
    }
};
