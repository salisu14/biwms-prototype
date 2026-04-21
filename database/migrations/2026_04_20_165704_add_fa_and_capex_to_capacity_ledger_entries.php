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
        Schema::table('capacity_ledger_entries', function (Blueprint $table) {
            $table->foreignId('fixed_asset_id')->nullable()->constrained('fixed_assets')->nullOnDelete();
            $table->foreignId('capex_project_id')->nullable()->constrained('capex_projects')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capacity_ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fixed_asset_id');
            $table->dropConstrainedForeignId('capex_project_id');
        });
    }
};
