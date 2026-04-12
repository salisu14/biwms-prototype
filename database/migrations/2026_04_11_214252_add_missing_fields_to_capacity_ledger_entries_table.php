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
            $table->renameColumn('document_number', 'document_no');
            $table->decimal('stop_time', 15, 4)->default(0)->after('run_time');
            $table->decimal('output_quantity', 15, 4)->default(0)->after('run_time_unit');
            $table->decimal('scrap_quantity', 15, 4)->default(0)->after('output_quantity');
            $table->decimal('unit_cost', 15, 4)->default(0)->after('overhead_cost');
            $table->index('document_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capacity_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(['document_no']);
            $table->dropColumn(['stop_time', 'output_quantity', 'scrap_quantity', 'unit_cost']);
            $table->renameColumn('document_no', 'document_number');
        });
    }
};
