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
        Schema::table('putaway_worksheet_lines', function (Blueprint $table) {
            $table->foreignId('bin_id')->nullable()->constrained('bins');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('putaway_worksheet_lines', function (Blueprint $table) {
            $table->dropColumn('bin_id');
        });
    }
};
