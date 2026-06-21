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
        Schema::create('purchase_quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_id')->constrained('purchase_quotes')->onDelete('cascade');
            $table->integer('line_no');
            $table->string('type', 20); // item, gl_account, resource, etc.
            $table->string('no', 20)->nullable(); // Item No, GL Account No, etc.
            $table->string('variant_code', 10)->nullable();
            $table->text('description');
            $table->text('description_2')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('outstanding_quantity', 18, 4)->default(0);
            $table->string('unit_of_measure_code', 10)->nullable();
            $table->decimal('direct_unit_cost', 18, 4);
            $table->decimal('unit_cost_lcy', 18, 4)->nullable();
            $table->decimal('line_discount_percent', 5, 2)->default(0);
            $table->decimal('line_discount_amount', 18, 4)->default(0);
            $table->decimal('line_amount', 18, 4);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->decimal('amount_including_vat', 18, 4)->default(0);
            $table->date('requested_receipt_date')->nullable();
            $table->date('promised_receipt_date')->nullable();
            $table->string('location_code', 10)->nullable();
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();
            $table->json('dimensions')->nullable();
            $table->decimal('quantity_to_receive', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4)->default(0);
            $table->foreignId('purchase_order_line_id')->nullable()->constrained('purchase_order_lines');
            $table->timestamps();

            $table->unique(['purchase_quote_id', 'line_no']);
            $table->index(['type', 'no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quote_lines');
    }
};
