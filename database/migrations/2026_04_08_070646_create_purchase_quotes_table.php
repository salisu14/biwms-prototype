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
        Schema::create('purchase_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('document_no', 20)->unique();
            $table->string('document_type', 20)->default('quote');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('contact_id')->nullable()->constrained('contacts');
            $table->foreignId('buyer_id')->nullable()->constrained('users');
            $table->string('vendor_quote_no', 35)->nullable();
            $table->date('document_date');
            $table->date('posting_date')->nullable();
            $table->date('order_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('requested_receipt_date')->nullable();
            $table->date('promised_receipt_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('currency_code', 10)->nullable();
            $table->decimal('currency_factor', 18, 6)->default(1);
            $table->string('payment_terms_code', 10)->nullable();
            $table->string('payment_method_code', 10)->nullable();
            $table->string('location_code', 10)->nullable();
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->json('dimensions')->nullable();
            $table->decimal('amount', 18, 4)->default(0);
            $table->decimal('amount_including_vat', 18, 4)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->text('vendor_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users');
            $table->string('quote_no', 20)->nullable(); // Reference to original quote when converted
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_no', 'document_type']);
            $table->index(['vendor_id', 'status']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quotes');
    }
};
