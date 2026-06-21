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
        Schema::create('payroll_statutory_setups', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. KENYA2024
            $table->decimal('personal_relief', 12, 2)->default(2400);
            $table->decimal('insurance_relief_percentage', 5, 2)->default(15);
            $table->json('income_tax_bands'); // [{limit: 24000, rate: 10}, ...]
            $table->decimal('nssf_tier1_limit', 12, 2)->default(7000);
            $table->decimal('nssf_tier1_rate', 5, 2)->default(6);
            $table->decimal('nssf_tier2_limit', 12, 2)->default(36000);
            $table->decimal('nssf_tier2_rate', 5, 2)->default(6);
            $table->decimal('nhif_rate', 5, 2)->default(2.75); // SHIF rate
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_statutory_setups');
    }
};
