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
        Schema::create('item_category_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->foreignId('item_id')
                ->constrained('items', 'id')
                ->onDelete('cascade');
            $table->foreignId('category_id')
                ->constrained('categories', 'id')
                ->onDelete('cascade');
            $table->boolean('is_primary')->default(false); // Primary category for reporting
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Prevent duplicate assignments
            $table->unique(['item_id', 'category_id']);

            // Query optimization
            $table->index(['item_id', 'is_primary']);
            $table->index(['category_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_category_assignments');
    }
};
