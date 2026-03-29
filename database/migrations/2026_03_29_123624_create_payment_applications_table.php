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
        Schema::create('payment_applications', function (Blueprint $table) {
            $table->id();

            // Parent Payment
            $table->foreignId('payment_id')
                ->constrained('payments')
                ->onDelete('cascade');

            // Document Being Paid (Invoice or Credit Memo)
            $table->enum('document_type', [
                'SALES_INVOICE',
                'SALES_CREDIT_MEMO',
                'PURCHASE_INVOICE',
                'PURCHASE_CREDIT_MEMO',
            ]);

            $table->unsignedBigInteger('document_id'); // Posted invoice/CM ID
            $table->string('document_number', 20);

            // Original Document Amounts (snapshot)
            $table->decimal('document_original_amount', 15, 4);
            $table->decimal('document_remaining_before', 15, 4);

            // Application Amounts
            $table->decimal('amount_applied', 15, 4); // How much of payment applied here
            $table->decimal('discount_applied', 15, 4)->default(0); // Discount taken
            $table->decimal('write_off_amount', 15, 4)->default(0); // Small balance write-off

            // Calculation
            $table->decimal('document_remaining_after', 15, 4);

            // Status
            $table->boolean('full_payment')->default(false); // Document fully paid after this?

            // User/Date
            $table->foreignId('applied_by')->constrained('users');
            $table->timestamp('applied_at');

            // Reversal (if payment application is undone)
            $table->boolean('reversed')->default(false);
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['payment_id', 'document_type', 'document_id']);
            $table->index(['document_type', 'document_id', 'reversed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_applications');
    }
};
