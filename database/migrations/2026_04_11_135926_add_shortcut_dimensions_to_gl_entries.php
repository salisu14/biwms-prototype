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
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->string('shortcut_dimension_1_code', 20)->nullable()->index();
            $table->string('shortcut_dimension_2_code', 20)->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gl_entries', function (Blueprint $table) {
            $table->dropIndex(['shortcut_dimension_1_code']);
            $table->dropIndex(['shortcut_dimension_2_code']);
            $table->dropColumn(['shortcut_dimension_1_code', 'shortcut_dimension_2_code']);
        });
    }
};
