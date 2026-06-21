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
        // 1. Create Salesperson/Purchaser table (BC Table 13)
        Schema::create('salesperson_purchasers', function (Blueprint $table) {
            $table->string('code', 20)->primary(); // e.g. 'JR'
            $table->string('name', 100);
            $table->decimal('commission_pct', 5, 2)->default(0);
            $table->string('phone_no', 30)->nullable();
            $table->string('email', 80)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Link Employees to Users
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::dropIfExists('salesperson_purchasers');
    }
};
