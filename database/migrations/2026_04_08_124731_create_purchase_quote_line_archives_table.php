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
        Schema::create('purchase_quote_line_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_quote_archive_id')->constrained()->onDelete('cascade');
            $table->integer('line_no');

            // Line fields (denormalized)
            $table->string('type', 20);
            $table->string('no', 20)->nullable();
            $table->string('variant_code', 10)->nullable();
            $table->text('description');
            $table->text('description_2')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('unit_of_measure_code', 10)->nullable();
            $table->decimal('direct_unit_cost', 18, 4);
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

            // Full JSON snapshot
            $table->json('line_data');

            $table->timestamps();

            $table->unique(['purchase_quote_archive_id', 'line_no']);
            $table->index(['type', 'no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_quote_line_archives');
    }
};
