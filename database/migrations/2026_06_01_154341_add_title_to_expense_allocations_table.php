<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_allocations', function (Blueprint $table) {
            $table->string('title', 120)->nullable()->after('expense_transaction_id');
        });

        DB::table('expense_allocations')
            ->whereNull('title')
            ->update(['title' => DB::raw('allocation_basis')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_allocations', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
