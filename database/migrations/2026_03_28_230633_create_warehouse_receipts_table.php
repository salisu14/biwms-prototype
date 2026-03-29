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
        Schema::create('warehouse_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 20)->unique();
            $table->foreignId('location_id')->constrained('locations');

            // Source Document
            $table->enum('source_document', array_column(\App\Enums\SourceDocument::cases(), 'value'));

            $table->unsignedBigInteger('source_document_id');
            $table->string('source_document_number', 20);

            // Vendor (if applicable)
            $table->foreignId('vendor_id')->nullable()->constrained('vendors');

            // Status
            $table->enum('status', array_column(\App\Enums\WarehouseReceiptStatus::cases(), 'value'))
                ->default('OPEN');

            // Assignment
            $table->foreignId('assigned_user_id')->nullable(); // Warehouse employee

            // Dates
            $table->date('receipt_date');
            $table->date('expected_receipt_date')->nullable();
            $table->timestamp('posted_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_receipts');
    }
};
