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
        Schema::table('items', function (Blueprint $table) {
            $table->string('inventory_method')->nullable();
            $table->foreignId('uom_id')->nullable()->constrained('unit_of_measures')->onDelete('set null');
            $table->foreignId('sku_id')->nullable()->constrained('item_skus')->onDelete('set null');
            $table->foreignId('vat_id')->nullable()->constrained('vat_masters')->onDelete('set null');
            $table->foreignId('general_posting_setup_id')->nullable()->constrained('general_posting_setups')->onDelete('set null');
            $table->foreignId('inventory_posting_setup_id')->nullable()->constrained('inventory_posting_setups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropForeign(['sku_id']);
            $table->dropForeign(['vat_id']);
            $table->dropForeign(['general_posting_setup_id']);
            $table->dropForeign(['inventory_posting_setup_id']);
            
            $table->dropColumn([
                'inventory_method',
                'uom_id', 
                'sku_id', 
                'vat_id', 
                'general_posting_setup_id', 
                'inventory_posting_setup_id'
            ]);
        });
    }
};
