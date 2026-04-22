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
        Schema::create('fa_journal_lines', function (Blueprint $table) {
            $table->id();

            // Parent Batch Link
            $table->foreignId('batch_id')
                ->constrained('fa_journal_batches')
                ->cascadeOnDelete();

            $table->integer('line_no');

            // Fixed Asset Identification
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->string('fa_no', 20)->nullable(); // Denormalized for quick reference/BC style

            // Posting Information
            $table->date('posting_date');
            $table->string('fa_posting_type'); // Acquisition, Depreciation, Disposal, etc.
            $table->string('document_no', 50);
            $table->string('description', 255)->nullable();

            // Financial Values (Precision: 4 decimal places for cost accuracy)
            $table->decimal('amount', 15, 4)->default(0);
            $table->decimal('calculated_amount', 15, 4)->default(0);

            // Depreciation Logic Fields
            $table->integer('number_of_duplication')->default(0);
            $table->decimal('number_of_depreciation_days', 15, 4)->nullable();
            $table->boolean('calculate_depreciation')->default(false);
            $table->decimal('index_factor', 15, 6)->default(0);

            // Specialized Posting Values
            $table->decimal('revaluation_amount', 15, 4)->nullable();
            $table->decimal('disposal_proceeds', 15, 4)->nullable();
            $table->date('disposal_date')->nullable();

            // Accounting Integration
            $table->foreignId('fa_posting_group_id')->nullable()->constrained('fa_posting_groups');
//            $table->foreignId('fa_posting_group_id')->constrained('fa_posting_groups');
            $table->foreignId('override_account_id')
                ->nullable()
                ->constrained('chart_of_accounts');

            // Dimensions & Tracking
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->json('dimension_set_entry')->nullable();

            // Status & Audit
            $table->string('line_status')->default('open'); // Enum: open, posted, error
            $table->unsignedBigInteger('fa_ledger_entry_id')->nullable(); // Link after posting
            $table->foreignId('created_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes for performance
            $table->index(['batch_id', 'line_no']);
            $table->index(['fixed_asset_id', 'posting_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('f_a_journal_lines');
    }
};
