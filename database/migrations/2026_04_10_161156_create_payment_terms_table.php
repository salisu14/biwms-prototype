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
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();

            // BC-standard fields
            $table->string('code', 20)->unique(); // 14D, 30D, 2/10NET30, COD, etc.
            $table->string('description', 100);
            $table->string('search_description', 100)->nullable();

            // Due date calculation (BC: Due Date Calculation)
            $table->string('calculation_type', 30)->default('net'); // PaymentTermsCalculation
            $table->integer('due_date_net_days')->default(0); // For NET type
            $table->integer('due_date_day_of_month')->nullable(); // For DUE_DATE type (1-31)
            $table->integer('due_date_months_ahead')->default(0); // For DUE_DAY type

            // Discount terms (BC: Payment Discount %, Discount Date Calculation)
            $table->boolean('discount_allowed')->default(false);
            $table->decimal('discount_percent', 5, 2)->default(0); // Early payment discount %
            $table->string('discount_calculation_type', 30)->nullable(); // PaymentTermsDiscountCalculation
            $table->integer('discount_net_days')->default(0); // Days for discount eligibility

            // Payment tolerance (BC: Payment Tolerance %)
            $table->boolean('payment_tolerance_enabled')->default(false);
            $table->decimal('payment_tolerance_percent', 5, 2)->default(0);
            $table->decimal('max_payment_tolerance_amount', 18, 4)->nullable();

            // Late payment
            $table->decimal('late_payment_penalty_percent', 5, 2)->default(0);
            $table->integer('late_payment_grace_days')->default(0);

            // Accounting
            $table->foreignId('discount_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('payment_tolerance_account_id')->nullable()->constrained('chart_of_accounts');

            // Dimensions
            $table->string('shortcut_dimension_1_code', 20)->nullable();
            $table->string('shortcut_dimension_2_code', 20)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('blocked')->default(false);

            // Metadata
            $table->text('notes')->nullable();
            $table->json('extended_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('blocked');
            $table->index('calculation_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
