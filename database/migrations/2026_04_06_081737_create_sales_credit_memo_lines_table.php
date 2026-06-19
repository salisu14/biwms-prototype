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
        Schema::create('sales_credit_memo_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_credit_memo_id')->constrained()->onDelete('cascade');

            // BC: Line No. (Standard increments of 10000)
            $table->integer('line_no')->default(10000);

            // BC: No. (Item or G/L Account)
            $table->foreignId('item_id')->constrained()->onDelete('cascade');

            // BC: Quantity (Standard Decimal 20,5)
            $table->decimal('quantity', 15, 5)->default(0);

            // BC: Unit of Measure Code
            $table->string('unit_of_measure_code', 10)->nullable();

            // BC: Unit Price (Standard Decimal 20,5)
            $table->decimal('unit_price', 15, 5)->default(0);

            // BC: Line Discount % & Line Discount Amount
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 15, 2)->default(0);

            // BC: VAT % & VAT Amount
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat_amount', 15, 2)->default(0);

            // BC: Amount (Excl. VAT)
            $table->decimal('amount', 15, 2)->default(0);

            // BC: Amount Including VAT (Gross)
            $table->decimal('amount_including_vat', 15, 2)->default(0);

            // BC: Appli-to Item Entry / Traceability
            $table->foreignId('sales_invoice_line_id')
                ->nullable()
                ->constrained('sales_invoice_lines')
                ->nullOnDelete();

            $table->timestamps();

            // Composite-like index for BC-style lookups
            $table->index(['sales_credit_memo_id', 'line_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_credit_memo_items');
    }
};
