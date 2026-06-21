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
        Schema::table('employee_compensation', function (Blueprint $table) {
            $table->text('audit_note')->nullable()->after('reason_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_compensation', function (Blueprint $table) {
            $table->dropColumn('audit_note');
        });
    }
};
