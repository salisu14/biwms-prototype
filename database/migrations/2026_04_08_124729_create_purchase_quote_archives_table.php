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
        Schema::create('purchase_quote_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_id')->constrained()->onDelete('cascade');
            $table->integer('version_no')->default(1);
            $table->string('document_no', 20);
            $table->string('document_type', 20)->default('quote');

            // Header fields (denormalized for quick access)
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('contact_id')->nullable()->constrained();
            $table->foreignId('buyer_id')->nullable()->constrained('users');
            $table->string('vendor_quote_no', 35)->nullable();
            $table->date('document_date');
            $table->date('posting_date')->nullable();
            $table->date('order_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20);
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

            // Archive metadata
            $table->timestamp('archived_at');
            $table->foreignId('archived_by')->constrained('users');
            $table->string('archive_reason', 50)->nullable(); // 'release', 'manual', 'restore_point'

            // Full JSON snapshot for complex restoration
            $table->json('quote_data');

            $table->timestamps();

            $table->unique(['purchase_quote_id', 'version_no']);
            $table->index(['document_no', 'version_no']);
            $table->index('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quote_archives');
    }
};
