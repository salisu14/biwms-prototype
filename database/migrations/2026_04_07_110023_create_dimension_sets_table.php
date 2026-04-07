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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dimension_sets');
    }
};
