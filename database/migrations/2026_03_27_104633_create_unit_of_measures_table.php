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
        Schema::create('unit_of_measures', function (Blueprint $table) {
            $table->id();
            $table->string('uom_code', 10)->unique();
            $table->string('description', 50);
            $table->decimal('conversion_factor', 18, 6)->default(1.0);
            $table->boolean('is_base_uom')->default(false);
            $table->timestamps();

            $table->index('uom_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_of_measures');
    }
};
