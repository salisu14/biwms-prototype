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
        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // BC: "Code"
            $table->string('name', 100); // BC: "Name"
            $table->string('code_caption', 100)->nullable(); // BC: "Code Caption"
            $table->string('filter_caption', 100)->nullable(); // BC: "Filter Caption"
            $table->string('description', 250)->nullable();
            $table->boolean('blocked')->default(false);
            $table->enum('dimension_type', ['global', 'shortcut', 'regular'])->default('regular');
            $table->tinyInteger('global_dimension_no')->nullable(); // 1-8 for shortcut dims
            $table->timestamps();

            $table->index('dimension_type');
            $table->index('global_dimension_no');
        });

        // Insert common BC dimensions
        DB::table('dimensions')->insert([
            ['code' => 'DEPARTMENT', 'name' => 'Department', 'code_caption' => 'Department Code', 'filter_caption' => 'Department Filter', 'dimension_type' => 'regular'],
            ['code' => 'AREA', 'name' => 'Area', 'code_caption' => 'Area Code', 'filter_caption' => 'Area Filter', 'dimension_type' => 'regular'],
            ['code' => 'PROJECT', 'name' => 'Project', 'code_caption' => 'Project Code', 'filter_caption' => 'Project Filter', 'dimension_type' => 'regular'],
            ['code' => 'SALESPERSON', 'name' => 'Salesperson', 'code_caption' => 'Salesperson Code', 'filter_caption' => 'Salesperson Filter', 'dimension_type' => 'regular'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimensions');
    }
};
