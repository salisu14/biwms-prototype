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
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();

            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained();

            // Financials
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('currency_code')->default('NGN');

            // Status lifecycle
            $table->string('status')->default('draft'); // draft, approved, posted, cancelled

            // Posting
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Dates
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            //            // Dimensions
            //            $table->foreignId('dimension_1_id')->nullable()->constrained('dimensions');
            //            $table->foreignId('dimension_2_id')->nullable()->constrained('dimensions');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
