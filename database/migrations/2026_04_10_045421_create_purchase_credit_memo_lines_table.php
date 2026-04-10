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
        Schema::create('purchase_credit_memo_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_credit_memo_id')->constrained('purchase_credit_memos')->cascadeOnDelete();
            
            $table->integer('line_number');
            $table->foreignId('item_id')->constrained('items');
            $table->string('item_code')->nullable();
            $table->string('description')->nullable();
            
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('line_total', 15, 4);
            $table->decimal('tax_percent', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4);
            
            $table->foreignId('general_product_posting_group_id')->nullable()->constrained('general_product_posting_groups');
            $table->string('unit_of_measure_code')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_credit_memo_lines');
    }
};
