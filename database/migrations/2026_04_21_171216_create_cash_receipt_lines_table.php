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
        Schema::create('cash_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_line_id')->constrained('journal_lines')->onDelete('cascade');
            // Cash Receipt specific fields
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('customer_no', 50)->nullable();
            $table->decimal('amount_received', 15, 4);
            $table->decimal('amount_received_lcy', 15, 4)->default(0);
            $table->decimal('remaining_amount', 15, 4)->default(0); // Unapplied amount
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->string('bank_account_no', 50)->nullable();
            $table->enum('applies_to_doc_type', ['Invoice', 'Credit Memo', 'Payment', 'Refund'])->nullable();
            $table->string('applies_to_doc_no', 50)->nullable();
            $table->foreignId('applies_to_id')->nullable(); // Customer ledger entry
            $table->decimal('applies_to_amount', 15, 4)->nullable();
            $table->boolean('calculate_vat')->default(false);
            $table->enum('payment_method_code', ['Cash', 'Check', 'Bank Transfer', 'Credit Card', 'Electronic'])->nullable();
            $table->string('check_no', 50)->nullable();
            $table->date('check_date')->nullable();
            $table->boolean('exported_to_payment_jnl')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_line_id')->constrained('journal_lines')->onDelete('cascade');
            // Payment Journal specific fields
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_no', 50)->nullable();
            $table->decimal('amount_paid', 15, 4);
            $table->decimal('amount_paid_lcy', 15, 4)->default(0);
            $table->decimal('remaining_amount', 15, 4)->default(0);
            $table->foreignId('bank_account_id')->constrained('bank_accounts');
            $table->string('bank_account_no', 50)->nullable();
            $table->enum('applies_to_doc_type', ['Invoice', 'Credit Memo', 'Payment', 'Refund'])->nullable();
            $table->string('applies_to_doc_no', 50)->nullable();
            $table->foreignId('applies_to_id')->nullable();
            $table->decimal('applies_to_amount', 15, 4)->nullable();
            $table->enum('payment_method_code', ['Cash', 'Check', 'Bank Transfer', 'Credit Card', 'Electronic'])->nullable();
            $table->string('check_no', 50)->nullable();
            $table->date('check_date')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('exported_to_payment_jnl')->default(false);
            $table->boolean('payment_processed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_receipt_lines');
    }
};
