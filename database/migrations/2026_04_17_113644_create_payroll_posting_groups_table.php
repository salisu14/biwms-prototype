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
        Schema::create('payroll_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description');
            
            // G/L Account mappings (BC-style)
            $table->foreignId('salaries_account_id')->constrained('chart_of_accounts'); // Debit
            $table->foreignId('wages_account_id')->nullable()->constrained('chart_of_accounts');
            $table->foreignId('social_security_account_id')->constrained('chart_of_accounts'); // Credit
            $table->foreignId('tax_payable_account_id')->constrained('chart_of_accounts'); // Credit
            $table->foreignId('net_pay_account_id')->constrained('chart_of_accounts'); // Credit (liability)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_posting_groups');
    }
};
