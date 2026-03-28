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
        Schema::create('general_posting_setups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description', 255);

            // Sales accounts
            $table->string('sales_account', 50);
            $table->string('sales_credit_account', 50)->nullable();
            $table->string('sales_discount_account', 50)->nullable();

            // Purchase accounts
            $table->string('purchase_account', 50);
            $table->string('purchase_credit_account', 50)->nullable();
            $table->string('purchase_discount_account', 50)->nullable();

            // COGS and variances
            $table->string('cogs_account', 50);
            $table->string('purchase_variance_account', 50)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_posting_setups');
    }
};
