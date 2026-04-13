<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure fa_posting_groups to align with BC FixedAsset Posting Group standard.
     * Drops old columns (applicability JSON, legacy account FKs) and adds
     * proper constrained GL account references + depreciation config fields.
     */
    public function up(): void
    {
        Schema::table('fa_posting_groups', function (Blueprint $table) {
            // Drop old columns that no longer apply
            $columns = [];

            foreach ([
                'acquisition_cost_offset_account_id',
                'depreciation_account_id',
                'maintenance_cost_account_id',
                'gain_on_disposal_account_id',
                'loss_on_disposal_account_id',
                'appreciation_account_id',
                'revaluation_gain_account_id',
                'applicable_tangible_types',
                'applicable_intangible_types',
                'applicable_liquidity_types',
            ] as $col) {
                if (Schema::hasColumn('fa_posting_groups', $col)) {
                    $columns[] = $col;
                }
            }

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('fa_posting_groups', function (Blueprint $table) {
            // Tighten existing columns
            $table->string('code', 20)->change();
            $table->string('description', 100)->nullable(false)->change();

            // Constrain existing nullable accounts that now become required
            if (Schema::hasColumn('fa_posting_groups', 'acquisition_cost_account_id')) {
                // Already exists — just ensure it's not nullable (handled by model validation)
            } else {
                $table->foreignId('acquisition_cost_account_id')->constrained('chart_of_accounts');
            }

            // New acquisition account
            $table->foreignId('acquisition_cost_account_id_lcy')->nullable()->constrained('chart_of_accounts');

            // Depreciation accounts
            if (! Schema::hasColumn('fa_posting_groups', 'depreciation_expense_account_id')) {
                $table->foreignId('depreciation_expense_account_id')->constrained('chart_of_accounts');
            }
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->constrained('chart_of_accounts');

            // Revaluation accounts
            $table->foreignId('revaluation_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('reversal_of_revaluation_id')->nullable()->constrained('chart_of_accounts');

            // Disposal accounts — rename gain/loss to new names
            if (! Schema::hasColumn('fa_posting_groups', 'disposal_proceeds_account_id')) {
                $table->foreignId('disposal_proceeds_account_id')->constrained('chart_of_accounts');
            }
            $table->foreignId('disposal_gain_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('disposal_loss_account_id')->nullable()->constrained('chart_of_accounts');

            // Capitalization (CWIP transfer)
            $table->foreignId('capitalization_account_id')->nullable()->constrained('chart_of_accounts');

            // Tax-specific depreciation accounts
            $table->foreignId('tax_depreciation_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('deferred_tax_account_id')->nullable()->constrained('chart_of_accounts');

            // Default depreciation settings
            $table->boolean('auto_depreciate_acquisition_year')->default(true);
            $table->enum('depreciation_calculation', ['full_year', 'pro_rata', 'half_year'])->default('pro_rata');
            $table->enum('depreciation_start', ['acquisition', 'first_day_next_month'])->default('acquisition');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fa_posting_groups', function (Blueprint $table) {
            $table->dropColumn([
                'acquisition_cost_account_id_lcy',
                'accumulated_depreciation_account_id',
                'revaluation_account_id',
                'reversal_of_revaluation_id',
                'disposal_gain_account_id',
                'disposal_loss_account_id',
                'capitalization_account_id',
                'tax_depreciation_account_id',
                'deferred_tax_account_id',
                'auto_depreciate_acquisition_year',
                'depreciation_calculation',
                'depreciation_start',
            ]);
        });

        Schema::table('fa_posting_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('acquisition_cost_offset_account_id')->nullable();
            $table->unsignedBigInteger('depreciation_account_id')->nullable();
            $table->unsignedBigInteger('maintenance_cost_account_id')->nullable();
            $table->unsignedBigInteger('gain_on_disposal_account_id')->nullable();
            $table->unsignedBigInteger('loss_on_disposal_account_id')->nullable();
            $table->unsignedBigInteger('appreciation_account_id')->nullable();
            $table->unsignedBigInteger('revaluation_gain_account_id')->nullable();
            $table->json('applicable_tangible_types')->nullable();
            $table->json('applicable_intangible_types')->nullable();
            $table->json('applicable_liquidity_types')->nullable();
        });
    }
};
