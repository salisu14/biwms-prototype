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
        Schema::create('general_ledger_setup', function (Blueprint $table) {
            $table->id();

            // Global Dimensions (1-2) - most important, stored in ledger entries
            $table->string('global_dimension_1_code', 20)->nullable();
            $table->string('global_dimension_2_code', 20)->nullable();

            // Shortcut Dimensions (3-8) - available on document lines
            $table->string('shortcut_dimension_3_code', 20)->nullable();
            $table->string('shortcut_dimension_4_code', 20)->nullable();
            $table->string('shortcut_dimension_5_code', 20)->nullable();
            $table->string('shortcut_dimension_6_code', 20)->nullable();
            $table->string('shortcut_dimension_7_code', 20)->nullable();
            $table->string('shortcut_dimension_8_code', 20)->nullable();

            // Localization
            $table->string('lc_code', 20)->nullable(); // Local Currency
            $table->string('company_name', 100);
            $table->timestamps();
        });

        // Insert default record
        DB::table('general_ledger_setup')->insert([
            'company_name' => 'Default Company',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_ledger_setups');
    }
};
