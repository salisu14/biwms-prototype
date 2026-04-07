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
        // Dimension Set Entries (BC Table 480)
        Schema::create('dimension_set_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_set_id')->constrained('dimension_sets');
            $table->string('dimension_code', 20); // BC: "Dimension Code"
            $table->string('dimension_value_code', 20); // BC: "Dimension Value Code"
            $table->string('dimension_name', 100)->nullable(); // CalcField
            $table->string('dimension_value_name', 100)->nullable(); // CalcField
            $table->timestamps();

            $table->unique(['dimension_set_id', 'dimension_code']);
            $table->index(['dimension_code', 'dimension_value_code']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_set_entries');
    }
};
