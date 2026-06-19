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
            $table->foreignId('inventory_bin_id')->nullable()->constrained('bins')->onDelete('set null')->after('bin_code');
            $table->string('sku')->nullable()->index()->after('item_code');
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->onDelete('set null')->after('inventory_posting_group_id');
            $table->foreignId('item_category_id')->nullable()->constrained('categories')->onDelete('set null')->after('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['inventory_bin_id']);
            $table->dropForeign(['currency_id']);
            $table->dropForeign(['item_category_id']);
            $table->dropColumn(['inventory_bin_id', 'sku', 'currency_id', 'item_category_id']);
        });
    }
};
