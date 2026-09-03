<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['production_orders', 'item_ledger_entries', 'capacity_ledger_entries'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'business_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('business_id')
                    ->nullable()
                    ->constrained('businesses')
                    ->nullOnDelete();
                $table->index('business_id');
            });
        }
    }

    public function down(): void
    {
        foreach (['production_orders', 'item_ledger_entries', 'capacity_ledger_entries'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'business_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('business_id');
            });
        }
    }
};
