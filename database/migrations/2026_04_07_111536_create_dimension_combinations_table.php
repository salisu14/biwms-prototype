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
        // Dimension Combinations (for Limited type)
        Schema::create('dimension_combinations', function (Blueprint $table) {
            $table->id();
            $table->string('dimension_1_code', 20);
            $table->string('dimension_2_code', 20);
            $table->enum('combination_type', ['no_limitation', 'limited', 'blocked'])->default('no_limitation');
            $table->timestamps();

            $table->unique(['dimension_1_code', 'dimension_2_code']);
            $table->index(['dimension_1_code', 'dimension_2_code', 'combination_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_combinations');
    }
};
