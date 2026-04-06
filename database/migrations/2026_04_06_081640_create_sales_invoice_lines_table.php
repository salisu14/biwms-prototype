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
        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained();

            $table->string('type')->default('ITEM'); // ITEM, GL_ACCOUNT, SERVICE

            $table->string('description')->nullable();

            // Quantity
            $table->decimal('quantity', 18, 4);
            $table->string('unit_of_measure')->nullable();

            // Pricing
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);

            // VAT
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);

            // Totals
            $table->decimal('line_total', 18, 2);

            // Inventory
            $table->foreignId('location_id')->nullable()->constrained();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
    }
};
