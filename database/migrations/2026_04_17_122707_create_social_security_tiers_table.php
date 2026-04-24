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
        Schema::create('social_security_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('tier_code', 20);
            $table->string('code', 50)->nullable();
            $table->decimal('from_salary', 12, 2);
            $table->decimal('to_salary', 12, 2)->nullable();
            $table->decimal('employee_rate', 5, 2);
            $table->decimal('employer_rate', 5, 2);

            $table->decimal('to_salary', 12, 2)->nullable()->change();
            $table->decimal('max_base', 15, 2)->nullable()->after('employer_rate');
            $table->decimal('employee_max_amount', 15, 2)->nullable()->after('max_base');
            $table->decimal('employer_max_amount', 15, 2)->nullable()->after('employee_max_amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_security_tiers');
    }
};
