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
        Schema::create('item_masters', function (Blueprint $table) {
            $table->id();
            $table->string('item_code', 50)->unique();
            $table->string('description', 255);
            $table->decimal('standard_cost', 18, 4)->default(0);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);

            $table->string('item_type', 20)->default('RAW_MATERIAL')->after('description');
            $table->string('inventory_method', 10)->default('FIFO')->after('item_type');


            $table->timestamps();

            $table->index(['item_code', 'is_active']);
            $table->index('item_type');
            $table->index('inventory_method');
            $table->index(['item_type', 'inventory_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_masters');
    }
};
