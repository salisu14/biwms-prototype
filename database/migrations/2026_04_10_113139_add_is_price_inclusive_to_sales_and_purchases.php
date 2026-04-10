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
        $tables = [
            'customers', 'vendors',
            'sales_orders', 'sales_quotes',
            'purchase_orders', 'purchase_quotes'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                // Not all tables might exist at once depending on migration state, but we know these core tables exist
                $table->boolean('is_price_inclusive')->default(false);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'customers', 'vendors',
            'sales_orders', 'sales_quotes',
            'purchase_orders', 'purchase_quotes'
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('is_price_inclusive');
            });
        }
    }

};
