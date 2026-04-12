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
        Schema::create('general_journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // e.g., "GENERAL", "DEFAULT", "ACCRUALS"
            $table->string('description', 100)->nullable();
            $table->enum('template_type', ['general', 'recurring', 'allocation'])->default('general');
            $table->foreignId('number_series_id')->constrained('number_series');
            $table->foreignId('posting_number_series_id')->nullable()->constrained('number_series'); // For posted entries
            $table->string('source_code', 20)->nullable(); // For transaction analysis (e.g., "GENJNL")
            $table->string('reason_code', 20)->nullable(); // Default reason code
            $table->foreignId('default_balancing_account_id')->nullable()->constrained('chart_of_accounts');
            $table->boolean('force_balancing_account')->default(false);
            $table->boolean('copy_dimensions_from_batch')->default(true);
            $table->boolean('suggest_balancing_amount')->default(true);
            $table->boolean('check_amount_sign')->default(true);
            $table->json('allowed_account_types')->nullable(); // ['asset', 'liability', 'equity', 'revenue', 'expense']
            $table->json('mandatory_dimensions')->nullable(); // ['department', 'project']
            $table->json('default_dimensions')->nullable(); // {"department": "ADM"}
            $table->boolean('test_report_before_posting')->default(false);
            $table->boolean('show_in_role_center')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('general_journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('general_journal_templates')->cascadeOnDelete();
            $table->string('name', 50); // e.g., "DEFAULT", "JOHN", "APRIL-ADJ"
            $table->string('description', 100)->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['open', 'released', 'posted', 'cancelled'])->default('open');
            $table->json('dimension_filter')->nullable(); // Restrict lines to specific dimensions
            $table->foreignId('balancing_account_id')->nullable()->constrained('chart_of_accounts');
            $table->string('reason_code', 20)->nullable();
            $table->boolean('copy_dimensions_from_line')->default(false);
            $table->timestamp('posting_date_restriction_from')->nullable();
            $table->timestamp('posting_date_restriction_to')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'name']);
        });

        Schema::create('general_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('general_journal_batches')->cascadeOnDelete();
            $table->integer('line_no')->default(10000);
            $table->date('posting_date');
            $table->string('document_type', 30)->nullable(); // Invoice, Credit Memo, Payment, etc.
            $table->string('document_no', 50)->nullable();
            $table->string('external_document_no', 50)->nullable();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('account_type', 20)->default('gl'); // gl, customer, vendor, bank, fixed_asset, ic_partner
            $table->foreignId('balancing_account_id')->nullable()->constrained('chart_of_accounts');
            $table->text('description');
            $table->decimal('debit_amount', 15, 4)->default(0);
            $table->decimal('credit_amount', 15, 4)->default(0);
            $table->decimal('amount_lcy', 15, 4)->default(0); // Local currency
            $table->string('currency_code', 10)->nullable();
            $table->decimal('currency_factor', 15, 6)->nullable();
            $table->decimal('amount_currency', 15, 4)->nullable();

            // Dimensions (BC Shortcut Dimensions 1-8)
            $table->string('shortcut_dimension_1_code', 50)->nullable(); // Global Dimension 1
            $table->string('shortcut_dimension_2_code', 50)->nullable(); // Global Dimension 2
            $table->json('dimension_set_entry')->nullable(); // Additional dimensions as JSON

            // Business information
            $table->foreignId('business_unit_id')->nullable()->constrained('business_units');
            $table->string('source_code', 20)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at');

            // Posting tracking
            $table->enum('line_status', ['open', 'checked', 'rejected', 'posted'])->default('open');
            $table->foreignId('posted_entry_id')->nullable(); // Polymorphic to posted entries
            $table->string('posted_entry_type')->nullable();

            $table->unique(['batch_id', 'line_no']);
            $table->index(['posting_date', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_journal_lines');
        Schema::dropIfExists('general_journal_batches');
        Schema::dropIfExists('general_journal_templates');
    }
};
