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
            $table->decimal('working_days', 8, 2)->default(30)->after('period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_documents', function (Blueprint $table) {
            $table->dropColumn('working_days');
        });
    }
};
