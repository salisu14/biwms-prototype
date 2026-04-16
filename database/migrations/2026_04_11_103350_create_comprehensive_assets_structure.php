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
//        // 1. Create FixedAsset Posting Groups Table
//        Schema::create('fa_posting_groups', function (Blueprint $table) {
//            $table->id();
//            $table->string('code')->unique();
//            $table->string('description')->nullable();
//
//            // Account IDs (Foreign keys to ChartOfAccounts or just integers for simplicity if relations aren't strict yet)
//            $table->unsignedBigInteger('acquisition_cost_account_id')->nullable();
//            $table->unsignedBigInteger('acquisition_cost_offset_account_id')->nullable();
//            $table->unsignedBigInteger('depreciation_account_id')->nullable();
//            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
//            $table->unsignedBigInteger('maintenance_expense_account_id')->nullable();
//            $table->unsignedBigInteger('maintenance_cost_account_id')->nullable();
//            $table->unsignedBigInteger('disposal_proceeds_account_id')->nullable();
//            $table->unsignedBigInteger('gain_on_disposal_account_id')->nullable();
//            $table->unsignedBigInteger('loss_on_disposal_account_id')->nullable();
//
//            $table->json('applicable_tangible_types')->nullable();
//            $table->json('applicable_intangible_types')->nullable();
//            $table->json('applicable_liquidity_types')->nullable();
//
//            $table->boolean('is_active')->default(true);
//            $table->timestamps();
//        });

        // 2. Create Assets Table
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // Classification
            $table->string('asset_type'); // Fixed or Liquidity
            $table->string('fixed_asset_category')->nullable(); // Tangible or Intangible
            $table->string('tangible_type')->nullable();
            $table->string('intangible_type')->nullable();
            $table->string('liquidity_type')->nullable();

            // Identification
            $table->string('asset_no')->unique();
            $table->string('description');
            $table->string('description_2')->nullable();
            $table->string('search_name')->nullable();

            // Cash/Bank specific
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_code')->nullable();
            $table->string('iban')->nullable();
            $table->string('swift_code')->nullable();

            // Receivables/Advances specific
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('reference_document_no')->nullable();
            $table->date('expected_clearance_date')->nullable();

            // Fixed asset specific
            $table->string('fa_location_code')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('registration_no')->nullable();
            $table->unsignedBigInteger('main_asset_id')->nullable();

            // Acquisition
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_cost', 20, 4)->default(0);
            $table->decimal('original_cost', 20, 4)->default(0);
            $table->unsignedBigInteger('acquisition_vendor_id')->nullable();
            $table->string('purchase_order_no')->nullable();
            $table->string('purchase_invoice_no')->nullable();

            // Status
            $table->boolean('active')->default(true);
            $table->boolean('acquired')->default(false);

            // Depreciation
            $table->string('depreciation_method')->nullable();
            $table->date('depreciation_start_date')->nullable();
            $table->date('depreciation_end_date')->nullable();
            $table->integer('useful_life_months')->nullable();
            $table->decimal('salvage_value', 20, 4)->default(0);
            $table->decimal('depreciation_rate', 10, 4)->nullable();
            $table->decimal('book_value', 20, 4)->default(0);
            $table->decimal('accumulated_depreciation', 20, 4)->default(0);
            $table->date('last_depreciation_date')->nullable();

            // Liquidity balances
            $table->decimal('opening_balance', 20, 4)->default(0);
            $table->decimal('current_balance', 20, 4)->default(0);
            $table->string('currency_code')->nullable();
            $table->decimal('currency_factor', 20, 6)->default(1);
            $table->date('last_reconciliation_date')->nullable();

            // GL accounts
            $table->unsignedBigInteger('fa_posting_group_id')->nullable();
            $table->unsignedBigInteger('asset_account_id')->nullable();
            $table->unsignedBigInteger('accum_dep_account_id')->nullable();
            $table->unsignedBigInteger('depreciation_expense_account_id')->nullable();
            $table->unsignedBigInteger('gain_loss_account_id')->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code')->nullable();
            $table->string('shortcut_dimension_2_code')->nullable();
            $table->json('dimensions')->nullable();

            // Disposal
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 20, 4)->default(0);
            $table->decimal('gain_loss_on_disposal', 20, 4)->default(0);

            $table->text('notes')->nullable();
            $table->json('custom_attributes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // 3. Data Migration from fixed_assets to assets
        if (Schema::hasTable('fixed_assets')) {
            $fixedAssets = DB::table('fixed_assets')->get();
            foreach ($fixedAssets as $fa) {
                DB::table('assets')->insert([
                    'asset_type' => 'fixed',
                    'fixed_asset_category' => 'tangible', // Default for legacy
                    'asset_no' => $fa->code,
                    'description' => $fa->description,
                    'fa_location_code' => $fa->location_code ?? null,
                    'acquisition_date' => $fa->acquisition_date,
                    'acquisition_cost' => $fa->acquisition_cost ?? 0,
                    'useful_life_months' => ($fa->useful_life_years ?? 0) * 12,
                    'depreciation_method' => $fa->depreciation_method,
                    'depreciation_rate' => $fa->depreciation_rate,
                    'active' => ($fa->status === 'ACTIVE'),
                    'acquired' => ! is_null($fa->acquisition_date),
                    'book_value' => $fa->net_book_value ?? 0,
                    'accumulated_depreciation' => $fa->accumulated_depreciation ?? 0,
                    'salvage_value' => $fa->salvage_value ?? 0,
                    'asset_account_id' => $fa->asset_gl_account_id,
                    'accum_dep_account_id' => $fa->accumulated_depreciation_gl_account_id,
                    'depreciation_expense_account_id' => $fa->depreciation_expense_gl_account_id,
                    'created_at' => $fa->created_at ?? now(),
                    'updated_at' => $fa->updated_at ?? now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
//        Schema::dropIfExists('fa_posting_groups');
    }
};
