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
        Schema::create('gl_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_number')->unique(); // Sequential
            $table->unsignedBigInteger('transaction_number'); // Groups entries

            // Account
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts');

            // Amount
            $table->decimal('debit_amount', 15, 2)->default(0);
            $table->decimal('credit_amount', 15, 2)->default(0);
            $table->decimal('amount', 15, 2); // Debit - Credit

            // Source
            $table->enum('source_type', array_column(\App\Enums\SourceType::cases(), 'value'))
                ->default('CUSTOMER');

            $table->string('source_number', 20)->nullable();

            // Document
            $table->string('document_type', 30);
            $table->string('document_number', 20);
            $table->date('document_date');

            // Posting
            $table->date('posting_date');
            $table->foreignId('user_id')->nullable(); // Who posted

            // Description
            $table->string('description');
            $table->string('comment')->nullable();

            // Dimensions
            $table->json('dimensions')->nullable();

            // Reconciliation
            $table->boolean('reconciled')->default(false);
            $table->date('reconciliation_date')->nullable();

            // Related Entries
            $table->unsignedBigInteger('item_ledger_entry_id')->nullable();
            $table->unsignedBigInteger('cust_ledger_entry_id')->nullable();
            $table->unsignedBigInteger('vendor_ledger_entry_id')->nullable();

            $table->timestamps();

            $table->index(['chart_of_account_id', 'posting_date']);
            $table->index(['transaction_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gl_entries');
    }
};
