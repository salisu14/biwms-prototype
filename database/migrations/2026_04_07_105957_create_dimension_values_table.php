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
        Schema::create('dimension_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dimension_id')->constrained('dimensions');
            $table->string('code', 20); // BC: "Code"
            $table->string('name', 100); // BC: "Name"
            $table->enum('dimension_value_type', ['standard', 'begin_total', 'end_total'])->default('standard');
            $table->foreignId('parent_id')->nullable()->constrained('dimension_values'); // Hierarchical
            $table->integer('indentation')->default(0); // For tree display
            $table->boolean('blocked')->default(false);
            $table->date('starting_date')->nullable();
            $table->date('ending_date')->nullable();

            // Financial reporting
            $table->string('global_dimension_no', 10)->nullable(); // If this is a global dim value

            $table->timestamps();

            // Composite unique key
            $table->unique(['dimension_id', 'code']);
            $table->index(['dimension_id', 'dimension_value_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_values');
    }
};
