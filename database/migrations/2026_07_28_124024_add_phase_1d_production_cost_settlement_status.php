<?php

declare(strict_types=1);

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
        if (! Schema::hasTable('production_orders')) {
            return;
        }

        Schema::table('production_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_orders', 'cost_settlement_status')) {
                $table->string('cost_settlement_status', 30)->default('pending')->after('cost_settlement_key');
            }

            if (! Schema::hasColumn('production_orders', 'cost_settlement_classification')) {
                $table->string('cost_settlement_classification', 50)->nullable()->after('cost_settlement_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('production_orders')) {
            return;
        }

        Schema::table('production_orders', function (Blueprint $table): void {
            foreach (['cost_settlement_classification', 'cost_settlement_status'] as $column) {
                if (Schema::hasColumn('production_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
