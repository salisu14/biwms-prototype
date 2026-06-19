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
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->enum('line_type', ['Earning', 'Deduction', 'Benefit'])->default('Earning')->after('pay_code_id');
            $table->decimal('hours', 8, 2)->nullable()->after('amount'); // For hourly pay codes
            $table->decimal('rate', 12, 4)->nullable()->after('hours'); // Hourly rate
            $table->decimal('employer_amount', 12, 2)->nullable()->after('rate'); // For benefits (employer portion)
            $table->boolean('posted_to_g_l')->default(false)->after('description');
            $table->timestamp('posted_at')->nullable()->after('posted_to_g_l');
            $table->foreignId('gl_entry_id')->nullable()->after('posted_at'); // Link to posted entry
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropColumn(['line_type', 'hours', 'rate', 'employer_amount', 'posted_to_g_l', 'posted_at', 'gl_entry_id']);
        });
    }
};
