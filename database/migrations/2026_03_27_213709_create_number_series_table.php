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
        Schema::create('number_series', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20)->unique(); // PURCHASE, PURCHASE_RETURN, etc.
            $table->string('description', 100);
            $table->string('prefix', 10)->default('P'); // P for purchase, PR for return, PI for invoice
            $table->integer('starting_number')->default(1);
            $table->integer('ending_number')->nullable(); // null = unlimited
            $table->integer('current_number')->default(0);
            $table->integer('year')->default(2026);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_manual')->default(false); // Allow manual entry
            $table->string('module', 20)->default('purchase'); // purchase, sales, inventory

            $table->timestamps();

            $table->index('code');
            $table->index(['module', 'is_active']);
            $table->index('year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_series');
    }
};
