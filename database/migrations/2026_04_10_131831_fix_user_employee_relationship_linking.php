<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add employee_id to users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('id')->constrained('employees')->nullOnDelete();
        });

        // 2. Transfer existing data from employees.user_id to users.employee_id
        DB::table('employees')
            ->whereNotNull('user_id')
            ->get()
            ->each(function ($employee) {
                DB::table('users')
                    ->where('id', $employee->user_id)
                    ->update(['employee_id' => $employee->id]);
            });

        // 3. Remove user_id from employees
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        DB::table('users')
            ->whereNotNull('employee_id')
            ->get()
            ->each(function ($user) {
                DB::table('employees')
                    ->where('id', $user->employee_id)
                    ->update(['user_id' => $user->id]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
