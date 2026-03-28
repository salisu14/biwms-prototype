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
        Schema::create('inventory_posting_setups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description', 255);

            // Main inventory accounts
            $table->string('inventory_account', 50);              // Asset account
            $table->string('inventory_adjmt_account', 50);      // Adjustments
            $table->string('invt_accrual_account', 50)->nullable(); // Interim/Accrual

            // COGS and applied costs
            $table->string('cogs_account', 50);
            $table->string('direct_cost_applied_account', 50)->nullable();
            $table->string('overhead_applied_account', 50)->nullable();

            // Variance accounts
            $table->string('purchase_variance_account', 50)->nullable();
            $table->string('material_variance_account', 50)->nullable();
            $table->string('capacity_variance_account', 50)->nullable();
            $table->string('subcontracted_variance_account', 50)->nullable();
            $table->string('cap_overhead_variance_account', 50)->nullable();
            $table->string('mfg_overhead_variance_account', 50)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_posting_setups');
    }
};
