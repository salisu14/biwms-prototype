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
        Schema::create('tax_brackets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_table_id')->constrained()->cascadeOnDelete();
            $table->decimal('from_amount', 15, 2);
            $table->decimal('to_amount', 15, 2)->nullable();
            $table->decimal('rate', 8, 4); // percentage rate
            $table->decimal('base_tax', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_brackets');
    }
};
