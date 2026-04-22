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
        // Journal Templates - The Archetype
        Schema::create('journal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique(); // e.g., 'GENERAL', 'ITEM', 'FA'
            $table->string('description', 100);
            $table->enum('type', [
                'General', 'Item', 'Resource', 'FixedAsset',
                'CashReceipt', 'Payment', 'Job', 'Warehouse', 'Recurring'
            ]);
            $table->boolean('recurring')->default(false);
            $table->string('source_code', 20)->nullable(); // Audit trail tag
            $table->string('no_series', 50)->nullable(); // Document numbering
            $table->string('posting_no_series', 50)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->boolean('copy_vat_setup_to_lines')->default(false);
            $table->boolean('allow_vat_difference')->default(false);
            $table->enum('bal_account_type', ['G/L', 'Customer', 'Vendor', 'Bank', 'FixedAsset'])->nullable();
            $table->string('bal_account_no', 50)->nullable();
            $table->string('page_id', 50)->nullable(); // UI reference
            $table->string('test_report_id', 50)->nullable();
            $table->string('posting_report_id', 50)->nullable();
            $table->boolean('copy_to_posted_jnl_lines')->default(false); // Historical tracking
            $table->timestamps();
        });

// Journal Batches - The Container
        Schema::create('journal_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_template_id')->constrained('journal_templates')->onDelete('cascade');
            $table->string('name', 50); // e.g., 'DEFAULT', 'PAYROLL', 'JANUARY'
            $table->string('description', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained(); // Assigned user
            $table->enum('bal_account_type', ['G/L', 'Customer', 'Vendor', 'Bank', 'FixedAsset'])->nullable();
            $table->string('bal_account_no', 50)->nullable();
            $table->string('no_series', 50)->nullable();
            $table->string('posting_no_series', 50)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->boolean('recurring')->default(false); // Inherited from template
            $table->timestamps();

            $table->unique(['journal_template_id', 'name']);
        });

// Base Journal Lines (Abstract - extended by domain-specific tables)
        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_batch_id')->constrained('journal_batches')->onDelete('cascade');
            $table->integer('line_no')->default(10000); // BC standard increment
            $table->date('posting_date');
            $table->date('document_date')->nullable();
            $table->string('document_type', 50)->nullable();
            $table->string('document_no', 50)->nullable();
            $table->string('external_document_no', 50)->nullable(); // For matching
            $table->enum('account_type', ['G/L', 'Customer', 'Vendor', 'Bank', 'FixedAsset', 'Item', 'Resource', 'Job']);
            $table->string('account_no', 50);
            $table->text('description');
            $table->decimal('amount', 15, 4)->default(0); // For single-amount journals
            $table->decimal('debit_amount', 15, 4)->default(0);
            $table->decimal('credit_amount', 15, 4)->default(0);
            $table->enum('bal_account_type', ['G/L', 'Customer', 'Vendor', 'Bank', 'FixedAsset'])->nullable();
            $table->string('bal_account_no', 50)->nullable();
            $table->string('currency_code', 10)->nullable();
            $table->decimal('currency_factor', 15, 8)->default(1);
            $table->decimal('amount_lcy', 15, 4)->default(0); // Local currency
            $table->json('dimensions')->nullable(); // Analytical tagging
            $table->string('shortcut_dim_1', 50)->nullable(); // Department
            $table->string('shortcut_dim_2', 50)->nullable(); // Project
            $table->string('source_code', 20)->nullable();
            $table->string('reason_code', 20)->nullable();
            $table->enum('status', ['Open', 'Posted', 'Reversed'])->default('Open');
            $table->timestamp('posted_at')->nullable();
            $table->string('posted_document_no', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_templates');
    }
};
