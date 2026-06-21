<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Depreciation Books (Multiple depreciation methods per asset - Book vs Tax)
        Schema::create('depreciation_books', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // FADB-001
            $table->string('description', 100);

            // Updated enum to match the 'corporate' value sent by the form
            $table->enum('book_type', ['corporate', 'tax', 'accounting', 'gaap', 'ifrs', 'custom'])->default('corporate');

            $table->boolean('is_default')->default(false);

            // Logic Methods
            $table->enum('default_depreciation_method', array_column(\App\Enums\DepreciationMethod::cases(), 'value'))->default('straight_line');
            $table->enum('default_calculation_method', array_column(\App\Enums\DepreciationCalculationMethod::cases(), 'value'))->default('straight_line');

            // Accounting Integration
            $table->boolean('integrate_with_gl')->default(true);
            $table->boolean('use_rounding')->default(true);
            $table->integer('rounding_precision')->default(2);

            // Fiscal Year
            $table->boolean('align_fiscal_year')->default(true);

            // Changed from enum to integer to accept the month number (1-12) sent by the form
            $table->integer('fiscal_year_start')->nullable();

            $table->boolean('is_active')->default(true);

            $table->decimal('acquisition_cost', 15, 4)->default(0)->after('is_active');
            $table->decimal('accumulated_depreciation', 15, 4)->default(0)->after('acquisition_cost');

            $table->timestamps();
        });

        // Fixed Assets Master
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('fa_no', 50)->unique(); // FA-00001
            $table->string('description', 100);
            $table->string('description_2', 100)->nullable();
            $table->string('search_description', 100)->nullable();

            // Classification
            $table->enum('fa_type', array_column(\App\Enums\FixedAssetType::cases(), 'value'))->default('fixed_asset');
            $table->foreignId('fa_class_id')->nullable()->constrained('fa_classes')->nullOnDelete();
            $table->foreignId('fa_subclass_id')->nullable()->constrained('fa_subclasses')->nullOnDelete();
            $table->foreignId('fa_location_id')->nullable()->constrained('fa_locations')->nullOnDelete();

            // Posting setup
            $table->foreignId('fa_posting_group_id')->constrained('fa_posting_groups');
            $table->foreignId('depreciation_book_id')->constrained('depreciation_books');

            // Physical tracking
            $table->string('serial_no', 100)->nullable();
            $table->string('barcode', 100)->nullable();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('users');
            $table->foreignId('vendor_id')->nullable()->constrained('vendors'); // Supplier
            $table->foreignId('main_vendor_id')->nullable()->constrained('vendors'); // Maintenance

            // Location tracking
            $table->foreignId('location_id')->nullable()->constrained('locations');
            $table->string('fa_location_code', 50)->nullable(); // Physical location code

            // Acquisition details
            $table->date('acquisition_date')->nullable();
            $table->date('depreciation_starting_date')->nullable();
            $table->date('depreciation_ending_date')->nullable();
            $table->decimal('acquisition_cost', 15, 4)->default(0);
            $table->foreignId('acquisition_vendor_id')->nullable()->constrained('vendors');
            $table->string('acquisition_invoice_no', 50)->nullable();

            // Depreciation setup
            $table->enum('depreciation_method', array_column(\App\Enums\DepreciationMethod::cases(), 'value'))->default('straight_line');
            $table->decimal('depreciation_rate', 7, 4)->nullable(); // Percentage
            $table->integer('useful_life_years')->nullable();
            $table->integer('useful_life_months')->nullable();
            $table->decimal('salvage_value', 15, 4)->default(0);
            $table->decimal('salvage_value_percentage', 5, 2)->nullable();

            // Units of production specific
            $table->decimal('total_estimated_units', 15, 4)->nullable();
            $table->decimal('units_produced_to_date', 15, 4)->default(0);

            // Declining balance specific
            $table->enum('declining_balance_calc', array_column(\App\Enums\DepreciationCalculationMethod::cases(), 'value'))->nullable();

            // Current values (denormalized for performance, recalculated periodically)
            $table->decimal('book_value', 15, 4)->default(0);
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('net_book_value', 15, 4)->virtualAs('book_value - accumulated_depreciation');

            // Revaluation
            $table->decimal('last_revaluation_amount', 15, 4)->nullable();
            $table->date('last_revaluation_date')->nullable();
            $table->decimal('revaluation_reserve', 15, 4)->default(0);

            // Insurance
            $table->decimal('insurance_value', 15, 4)->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->string('insurance_policy_no', 50)->nullable();

            // Status
            $table->enum('status', array_column(\App\Enums\FAStatus::cases(), 'value'))->default('new');
            $table->boolean('blocked')->default(false);
            $table->text('blocked_reason')->nullable();

            // Disposal info
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 4)->nullable();
            $table->decimal('disposal_cost', 15, 4)->nullable();
            $table->decimal('disposal_gain_loss', 15, 4)->nullable();

            // Dimensions
            $table->string('shortcut_dimension_1_code', 50)->nullable();
            $table->string('shortcut_dimension_2_code', 50)->nullable();
            $table->json('dimension_set_entry')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fa_type', 'status']);
            $table->index(['fa_posting_group_id', 'depreciation_book_id']);
            $table->index(['acquisition_date', 'depreciation_starting_date']);
        });

        Schema::create('fa_ledger_entries', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->foreignId('depreciation_book_id')->constrained('depreciation_books');

            // BC-style entry number
            $table->integer('entry_no');
            $table->unique(['fixed_asset_id', 'depreciation_book_id', 'entry_no'], 'fa_ledger_unique_entry');

            // Posting type
            $table->enum('fa_posting_type', array_column(\App\Enums\FAPostingType::cases(), 'value'));

            // Document references
            $table->string('document_type', 50)->nullable();
            $table->string('document_no', 50)->nullable();
            $table->integer('document_line_no')->nullable();
            $table->date('posting_date');
            $table->foreignId('gl_entry_id')->nullable()->constrained('gl_entries');

            // Amounts
            $table->decimal('amount', 15, 4);
            $table->decimal('amount_lcy', 15, 4);

            // Depreciation specific
            $table->decimal('depreciation_amount', 15, 4)->default(0);
            $table->decimal('accumulated_depreciation', 15, 4)->default(0);
            $table->decimal('book_value_after', 15, 4);
            $table->decimal('number_of_depreciation_days', 8, 2)->nullable();
            $table->integer('depreciation_period')->nullable();

            // Revaluation specific
            $table->decimal('revaluation_amount', 15, 4)->default(0);
            $table->decimal('index_factor', 10, 6)->nullable();

            // Disposal specific
            $table->decimal('proceeds_on_disposal', 15, 4)->default(0);
            $table->decimal('gain_loss_on_disposal', 15, 4)->default(0);

            // Description and notes
            $table->text('description');
            $table->text('comment')->nullable();

            // Source
            $table->string('source_code', 20)->nullable();
            $table->foreignId('journal_batch_id')->nullable();
            $table->string('journal_batch_type', 50)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('entry_timestamp');

            // Reversal tracking - ADD THESE COLUMNS HERE
            $table->foreignId('reversed_entry_fixed_asset_id')->nullable();
            $table->foreignId('reversed_entry_depreciation_book_id')->nullable();
            $table->integer('reversed_entry_no')->nullable();

            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['fixed_asset_id', 'depreciation_book_id', 'posting_date']);
            $table->index(['fa_posting_type', 'posting_date']);
            $table->index(['document_type', 'document_no']);
            $table->index(['reversed_entry_fixed_asset_id', 'reversed_entry_depreciation_book_id', 'reversed_entry_no']);
        });

        // NOW add the foreign key constraint (columns exist)
        Schema::table('fa_ledger_entries', function (Blueprint $table) {
            $table->foreign(['reversed_entry_fixed_asset_id', 'reversed_entry_depreciation_book_id', 'reversed_entry_no'])
                ->references(['fixed_asset_id', 'depreciation_book_id', 'entry_no'])
                ->on('fa_ledger_entries')
                ->nullOnDelete();
        });

        // FA Journal Templates (Similar to other journal templates)
        Schema::create('fa_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // FA-GENERAL, FA-DEPRECIATION, FA-DISPOSAL
            $table->string('description', 100)->nullable();
            $table->enum('template_type', ['acquisition', 'depreciation', 'revaluation', 'disposal', 'maintenance'])->default('acquisition');
            $table->foreignId('number_series_id')->constrained('number_series');
            $table->foreignId('posting_number_series_id')->nullable()->constrained('number_series');
            $table->string('source_code', 20)->nullable(); // FAJNL
            $table->foreignId('default_depreciation_book_id')->constrained('depreciation_books');
            $table->boolean('test_report_before_posting')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fa_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('fa_journal_templates')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('description', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'released', 'posted', 'cancelled'])->default('open');
            $table->foreignId('depreciation_book_id')->nullable()->constrained('depreciation_books');
            $table->date('posting_date')->nullable(); // For depreciation runs
            $table->boolean('calculate_depreciation')->default(false); // Batch calculates before posting
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });


        // FA Insurance Coverage
        Schema::create('fa_insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('policy_no', 50);
            $table->foreignId('insurance_vendor_id')->constrained('vendors');
            $table->decimal('coverage_amount', 15, 4);
            $table->decimal('premium_amount', 15, 4);
            $table->date('start_date');
            $table->date('expiry_date');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fa_insurance_policies');
        Schema::dropIfExists('fa_journal_lines');
        Schema::dropIfExists('fa_journal_batches');
        Schema::dropIfExists('fa_journal_templates');
        Schema::dropIfExists('depreciation_books');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fa_ledger_entries');
    }
};
