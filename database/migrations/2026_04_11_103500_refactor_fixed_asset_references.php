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
        // Update purchase_order_lines
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->renameColumn('fixed_asset_id', 'asset_id');
            // We'll keep it as a foreignId but we won't constrain it until we're sure about the assets table state
            // Re-constrain or just keep as unsignedBigInteger
        });

        // Update posted_purchase_invoice_lines
        Schema::table('posted_purchase_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->renameColumn('fixed_asset_id', 'asset_id');
        });

        // Update vendor_invoice_lines
        Schema::table('vendor_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->renameColumn('fixed_asset_id', 'asset_id');
        });

        // Update capex_projects
        Schema::table('capex_projects', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->renameColumn('fixed_asset_id', 'asset_id');
        });

        // Update fixed_asset_depreciation_ledger
        Schema::table('fixed_asset_depreciation_ledger', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->renameColumn('fixed_asset_id', 'asset_id');
            $table->rename('asset_depreciation_ledger');
        });

        // Drop the old table
        Schema::dropIfExists('fixed_assets');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Complex to reverse, but basically rename back and recreate table
    }
};
