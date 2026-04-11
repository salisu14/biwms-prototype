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
        Schema::table('posted_purchase_invoice_lines', function (Blueprint $table) {
            $table->string('type', 20)->default('item')->after('po_line_number');
            $table->foreignId('fixed_asset_id')->nullable()->after('item_id')->constrained('fixed_assets');
            $table->string('fa_posting_type', 20)->nullable()->after('fixed_asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('posted_purchase_invoice_lines', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->dropColumn(['type', 'fixed_asset_id', 'fa_posting_type']);
        });
    }
};
