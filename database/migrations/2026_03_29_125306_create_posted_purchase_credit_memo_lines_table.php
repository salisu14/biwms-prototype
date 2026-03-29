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
        Schema::create('posted_purchase_credit_memo_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_memo_id')
                ->constrained('posted_purchase_credit_memos')
                ->onDelete('cascade');
            $table->integer('line_number');

            $table->string('type'); // ITEM, GL_ACCOUNT, RESOURCE
            $table->foreignId('item_id')->nullable()->constrained('items');
            $table->foreignId('gl_account_id')->nullable()->constrained('gl_accounts');
            $table->text('description');

            $table->decimal('quantity', 15, 4);
            $table->string('unit_of_measure')->nullable();
            $table->decimal('unit_price', 15, 4);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('amount', 15, 4);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);

            // Posting Groups per line
            $table->foreignId('general_product_posting_group_id')
                ->nullable()
                ->constrained('general_product_posting_groups');
            $table->foreignId('inventory_posting_group_id')
                ->nullable()
                ->constrained('inventory_posting_groups');
            $table->foreignId('tax_group_id')->nullable();

            $table->json('dimensions')->nullable();

            $table->foreignId('corrected_invoice_line_id')
                ->nullable()
                ->constrained('posted_purchase_invoice_lines');

            $table->timestamps();

            $table->index(['credit_memo_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posted_purchase_credit_memo_lines');
    }
};
