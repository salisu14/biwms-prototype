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
        Schema::create('employee_posting_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('description')->nullable();
            
            // GL Account for Payable Salary/Expenses
            $table->foreignId('payables_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            
            $table->boolean('blocked')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_posting_groups');
    }
};
