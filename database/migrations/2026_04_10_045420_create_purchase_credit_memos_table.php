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
        Schema::create('purchase_credit_memos', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->string('external_document_number')->nullable();
            
            // Vendor
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('vendor_name');
            
            // Correction
            $table->foreignId('corrects_invoice_id')->nullable()->constrained('posted_purchase_invoices');
            $table->string('corrects_invoice_number')->nullable();
            
            // Financials
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->string('currency_code')->default('USD');
            
            // Posting Info
            $table->date('posting_date')->nullable();
            $table->date('document_date')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations');
            
            // Lifecycle/Approval
            $table->string('status')->default('DRAFT'); // DRAFT, PENDING, APPROVED, REJECTED, POSTED
            $table->string('rejection_reason')->nullable();
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            
            $table->text('reason_code')->nullable();
            $table->text('description')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_memos');
    }
};
