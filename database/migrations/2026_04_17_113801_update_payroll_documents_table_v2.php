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
        Schema::table('payroll_documents', function (Blueprint $table) {
            $table->foreignId('payroll_period_id')->nullable()->after('document_number')->constrained('payroll_periods');
            $table->string('status')->change(); // To accommodate new enum values if changed from enum to string or just update
            $table->decimal('total_earnings', 12, 2)->default(0)->after('remarks');
            $table->decimal('total_deductions', 12, 2)->default(0)->after('total_earnings');
            $table->decimal('total_net_pay', 12, 2)->default(0)->after('total_deductions');
            $table->foreignId('approved_by')->nullable()->after('total_net_pay')->constrained('employees');
            $table->decimal('working_days', 8, 2)->default(30);
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_documents', function (Blueprint $table) {
            $table->dropForeign(['payroll_period_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['payroll_period_id', 'total_earnings', 'total_deductions', 'total_net_pay', 'approved_by', 'approved_at']);
        });
    }
};
