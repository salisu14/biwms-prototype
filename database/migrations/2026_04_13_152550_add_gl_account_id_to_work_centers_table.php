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
        Schema::table('work_centers', function (Blueprint $table) {
            $table->foreignId('work_center_gl_account_id')
                ->nullable()
                ->after('work_center_account_no')
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->dropForeignIdFor('work_center_gl_account_id');
            $table->dropColumn('work_center_gl_account_id');
        });
    }
};
