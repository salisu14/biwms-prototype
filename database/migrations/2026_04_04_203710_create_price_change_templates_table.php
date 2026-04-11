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
        Schema::create('price_change_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('adjustment_type', ['increase', 'decrease', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->enum('status', ['draft', 'approved', 'applied'])->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->enum('base', ['cost', 'price']);
            $table->decimal('rounding', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_change_templates');
    }
};
