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
        Schema::create('allocations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->decimal('total_percentage', 5, 2)->default(100);
            $table->timestamps();
        });

        Schema::create('allocation_lines', function (Blueprint $table) {
            $table->id();

            // Link to the Header in the Canvas
            $table->foreignId('allocation_id')
                ->constrained('allocations')
                ->onDelete('cascade');

            // The G/L Account where the split amount will be posted
            $table->foreignId('target_account_id')
                ->constrained('chart_of_accounts');

            $table->string('description')->nullable();

            // The split percentage (e.g., 25.00 for 25%)
            $table->decimal('percentage', 5, 2);

            // Optional: Dimension support for cost center tracking
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('allocation_lines');
        Schema::dropIfExists('allocations');
    }
};
