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
        Schema::table('tax_tables', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('country_code', 10)->nullable()->after('name');
            $table->string('state_code', 10)->nullable()->after('country_code');
            
            // Drop old flat band columns
            $table->dropColumn(['from_amount', 'to_amount', 'base_tax', 'percentage']);
        });

        Schema::table('social_security_tiers', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('tier_code');
            $table->decimal('to_salary', 12, 2)->nullable()->change();
            $table->decimal('max_base', 15, 2)->nullable()->after('employer_rate');
            $table->decimal('employee_max_amount', 15, 2)->nullable()->after('max_base');
            $table->decimal('employer_max_amount', 15, 2)->nullable()->after('employee_max_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tax_tables', function (Blueprint $table) {
            $table->dropColumn(['name', 'country_code', 'state_code']);
            $table->decimal('from_amount', 12, 2)->nullable();
            $table->decimal('to_amount', 12, 2)->nullable();
            $table->decimal('base_tax', 12, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
        });

        Schema::table('social_security_tiers', function (Blueprint $table) {
            $table->dropColumn(['code', 'max_base', 'employee_max_amount', 'employer_max_amount']);
        });
    }
};
