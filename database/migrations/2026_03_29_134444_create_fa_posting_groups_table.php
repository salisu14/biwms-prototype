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
        Schema::create('fa_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description', 100);

            // --- Acquisition accounts ---
            $table->foreignId('acquisition_cost_account_id')->constrained('chart_of_accounts');
            $table->foreignId('acquisition_cost_account_id_lcy')->nullable()->constrained('chart_of_accounts');

            // --- Depreciation accounts ---
            $table->foreignId('depreciation_expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts');

            // --- Revaluation accounts ---
            $table->foreignId('revaluation_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('reversal_of_revaluation_id')->nullable()->constrained('chart_of_accounts');

            // --- Disposal accounts ---
            $table->foreignId('disposal_proceeds_account_id')->constrained('chart_of_accounts');
            $table->foreignId('disposal_gain_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('disposal_loss_account_id')->nullable()->constrained('chart_of_accounts');

            // --- Maintenance & Capitalization accounts ---
            $table->foreignId('maintenance_expense_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('capitalization_account_id')->nullable()->constrained('chart_of_accounts');

            // --- Tax accounts ---
            $table->foreignId('tax_depreciation_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('deferred_tax_account_id')->nullable()->constrained('chart_of_accounts');

            // --- Default depreciation settings & Flags ---
            $table->boolean('auto_depreciate_acquisition_year')->default(true);
            $table->enum('depreciation_calculation', ['full_year', 'pro_rata', 'half_year'])->default('pro_rata');
            $table->enum('depreciation_start', ['acquisition', 'first_day_next_month'])->default('acquisition');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fa_posting_groups');
    }
};
