<?php

declare(strict_types=1);

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
            $table->foreignId('attendance_payroll_review_batch_line_id')->nullable()->after('gl_entry_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendance_payroll_review_batch_line_id');
        });
    }
};
