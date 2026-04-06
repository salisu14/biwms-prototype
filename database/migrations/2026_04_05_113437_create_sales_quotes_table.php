<?php

use App\Enums\QuoteStatus;
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
        Schema::create('sales_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_no')->unique();
            $table->foreignId('customer_id')->constrained();
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);

            // Using the enum for the status column
            $table->string('status')->default(QuoteStatus::DRAFT->value);

            $table->string('approval_status')->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_quotes');
    }
};
