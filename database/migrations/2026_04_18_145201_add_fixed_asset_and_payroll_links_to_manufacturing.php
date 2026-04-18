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
        Schema::table('work_centers', function (Blueprint $table) {
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete();
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
        });

        Schema::table('machine_centers', function (Blueprint $table) {
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete();
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_asset_id');
            $table->dropConstrainedForeignId('operator_employee_id');
        });

        Schema::table('machine_centers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_asset_id');
            $table->dropConstrainedForeignId('operator_employee_id');
        });
    }
};
