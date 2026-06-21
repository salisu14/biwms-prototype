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
        Schema::create('petty_cash_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_voucher_id')->constrained()->cascadeOnDelete();
            $table->integer('line_number');
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->foreignId('dimension_department_id')->nullable()->constrained('dimensions');
            $table->foreignId('dimension_project_id')->nullable()->constrained('dimensions');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_voucher_lines');
    }
};
