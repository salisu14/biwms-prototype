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
        Schema::table('expense_budgets', function (Blueprint $table) {
            $table->foreignId('dimension_set_id')->nullable()->constrained('dimension_sets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_budgets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dimension_set_id');
        });
    }
};
