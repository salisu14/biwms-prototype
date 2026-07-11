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
        Schema::table('employee_attendance_days', function (Blueprint $table) {
            $table->foreignId('locked_by_review_period_id')->nullable()->after('attendance_ledger_entry_id')->constrained('attendance_review_periods')->nullOnDelete();
            $table->timestamp('locked_at')->nullable()->after('locked_by_review_period_id');
            $table->string('locked_snapshot_hash')->nullable()->after('locked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendance_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_by_review_period_id');
            $table->dropColumn(['locked_at', 'locked_snapshot_hash']);
        });
    }
};
