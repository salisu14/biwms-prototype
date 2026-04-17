<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE pay_codes DROP CONSTRAINT IF EXISTS pay_codes_calculation_method_check');
            DB::statement('ALTER TABLE pay_codes DROP CONSTRAINT IF EXISTS pay_codes_type_check');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No easy way to restore them exactly as they were without knowing all cases
    }
};
