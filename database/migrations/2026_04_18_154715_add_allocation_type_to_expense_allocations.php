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
        Schema::table('expense_allocations', function (Blueprint $table) {
            $table->string('allocation_type')->default('percentage')->comment('percentage, amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_allocations', function (Blueprint $table) {
            $table->dropColumn('allocation_type');
        });
    }
};
