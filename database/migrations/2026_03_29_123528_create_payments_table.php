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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Payment Identification
            $table->string('payment_number', 20)->unique();
            $table->string('external_reference', 50)->nullable(); // Bank ref, check number

            // Payment Type (AR or AP)
            $table->enum('payment_direction', [
                'RECEIPT',      // From Customer (AR)
                'DISBURSEMENT', // To Vendor (AP)
            ]);

            // Party (Customer or Vendor)
            $table->enum('party_type', ['CUSTOMER', 'VENDOR']);
            $table->foreignId('party_id'); // customer_id or vendor_id
            $table->string('party_name', 100);

            // Payment Method
            $table->enum('payment_method', [
                'CASH',
                'CHECK',
                'BANK_TRANSFER',
                'ACH',
                'WIRE',
                'CREDIT_CARD',
                'DEBIT_CARD',
                'MOBILE_MONEY',
                'CRYPTO',
                'OTHER',
            ]);

            // Bank/Account Information
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts');
            $table->string('bank_account_number', 50)->nullable();
            $table->string('check_number', 50)->nullable();
            $table->date('check_date')->nullable();

            // Counterparty Bank (for wires/transfers)
            $table->string('counterparty_bank_name', 100)->nullable();
            $table->string('counterparty_account_number', 50)->nullable();
            $table->string('counterparty_routing_number', 20)->nullable();

            // Amounts
            $table->decimal('payment_amount', 15, 4);
            $table->decimal('applied_amount', 15, 4)->default(0);
            $table->decimal('unapplied_amount', 15, 4)->default(0);

            // Currency
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_factor', 15, 6)->default(1);
            $table->decimal('payment_amount_lcy', 15, 4); // Local currency

            // Discounts
            $table->decimal('discount_taken', 15, 4)->default(0);
            $table->string('discount_reason', 50)->nullable(); // Early payment, volume, etc.

            // Fees (for credit cards, wires)
            $table->decimal('transaction_fee', 15, 4)->default(0);
            $table->decimal('transaction_fee_lcy', 15, 4)->default(0);

            // Dates
            $table->date('payment_date'); // When payment was made/received
            $table->date('posting_date'); // Accounting date
            $table->date('value_date')->nullable(); // When funds available
            $table->date('clearing_date')->nullable(); // When check clears

            // Status
            $table->enum('status', [
                'PENDING',
                'POSTED',
                'CLEARED',
                'RECONCILED',
                'VOIDED',
                'RETURNED', // Check bounced
            ])->default('PENDING');

            // Reconciliation
            $table->boolean('reconciled')->default(false);
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users');
            $table->foreignId('bank_statement_line_id')->nullable();

            // Posting Groups
            $table->foreignId('general_business_posting_group_id')
                ->nullable()
                ->constrained('general_business_posting_groups');

            // For AR: Customer Posting Group
            // For AP: Vendor Posting Group
            $table->foreignId('posting_group_id')->nullable(); // customer or vendor posting group

            // User Tracking
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();

            // Void Information
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->string('void_reason', 200)->nullable();

            // Notes
            $table->text('internal_notes')->nullable();
            $table->text('memo')->nullable(); // Check memo, wire reference

            // Dimensions
            $table->json('dimensions')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['party_type', 'party_id', 'payment_date']);
            $table->index(['payment_direction', 'status']);
            $table->index(['bank_account_id', 'reconciled']);
            $table->index(['payment_method', 'status']);
            $table->index(['external_reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
