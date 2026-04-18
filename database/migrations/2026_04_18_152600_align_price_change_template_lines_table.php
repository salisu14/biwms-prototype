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
        // Fix table name mismatch in existing code
        if (Schema::hasTable('price_change_template_items') && ! Schema::hasTable('price_change_template_lines')) {
            Schema::rename('price_change_template_items', 'price_change_template_lines');
        }

        Schema::table('price_change_template_lines', function (Blueprint $table) {
            $table->decimal('current_unit_price', 18, 4)->default(0)->after('item_id');
            $table->decimal('new_unit_price', 18, 4)->default(0)->after('current_unit_price');
            $table->decimal('adjustment_percent', 18, 4)->default(0)->after('new_unit_price');
            $table->decimal('adjustment_amount', 18, 4)->default(0)->after('adjustment_percent');
            $table->timestamp('applied_at')->nullable()->after('adjustment_amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_change_template_lines', function (Blueprint $table) {
            $table->dropColumn([
                'current_unit_price',
                'new_unit_price',
                'adjustment_percent',
                'adjustment_amount',
                'applied_at',
            ]);
            $table->dropTimestamps();
        });

        if (Schema::hasTable('price_change_template_lines')) {
            Schema::rename('price_change_template_lines', 'price_change_template_items');
        }
    }
};
