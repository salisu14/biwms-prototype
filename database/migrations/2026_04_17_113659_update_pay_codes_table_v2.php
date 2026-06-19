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
        Schema::table('pay_codes', function (Blueprint $table) {
            $table->decimal('default_percentage', 5, 2)->nullable()->after('default_amount');
            $table->boolean('taxable')->default(true)->after('default_percentage');
            $table->boolean('is_statutory')->default(false)->after('taxable');
            
            // Update existing enum columns to reflect new cases
            $table->string('type')->change();
            $table->string('calculation_method')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pay_codes', function (Blueprint $table) {
            $table->dropColumn(['default_percentage', 'taxable', 'is_statutory']);
        });
    }
};
