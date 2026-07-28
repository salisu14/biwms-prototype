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
        if (! Schema::hasTable('inventory_posting_setups')) {
            return;
        }

        Schema::table('inventory_posting_setups', function (Blueprint $table): void {
            if (! Schema::hasColumn('inventory_posting_setups', 'inventory_in_transit_account_id')) {
                $table->foreignId('inventory_in_transit_account_id')
                    ->nullable()
                    ->after('inventory_account_interim_id')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_posting_setups', 'transfer_gain_account_id')) {
                $table->foreignId('transfer_gain_account_id')
                    ->nullable()
                    ->after('inventory_in_transit_account_id')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_posting_setups', 'transfer_loss_account_id')) {
                $table->foreignId('transfer_loss_account_id')
                    ->nullable()
                    ->after('transfer_gain_account_id')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('inventory_posting_setups', 'transfer_variance_account_id')) {
                $table->foreignId('transfer_variance_account_id')
                    ->nullable()
                    ->after('transfer_loss_account_id')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('inventory_posting_setups')) {
            return;
        }

        Schema::table('inventory_posting_setups', function (Blueprint $table): void {
            foreach ([
                'transfer_variance_account_id',
                'transfer_loss_account_id',
                'transfer_gain_account_id',
                'inventory_in_transit_account_id',
            ] as $column) {
                if (Schema::hasColumn('inventory_posting_setups', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }
};
