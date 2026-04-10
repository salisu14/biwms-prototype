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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 20)->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->virtualAs('first_name || \' \' || last_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            
            // Organizational Tracking (Dimensions)
            $table->string('business_code')->nullable()->index();
            $table->string('factory_code')->nullable()->index();
            $table->string('department_code')->nullable()->index();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
