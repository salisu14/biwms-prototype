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
            $table->foreignId('workforce_roster_assignment_id')->nullable()->after('attendance_ledger_entry_id')->constrained('workforce_roster_assignments')->nullOnDelete();
            $table->string('schedule_source')->nullable()->after('workforce_roster_assignment_id');
            $table->string('schedule_version')->nullable()->after('schedule_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_attendance_days', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workforce_roster_assignment_id');
            $table->dropColumn(['schedule_source', 'schedule_version']);
        });
    }
};
