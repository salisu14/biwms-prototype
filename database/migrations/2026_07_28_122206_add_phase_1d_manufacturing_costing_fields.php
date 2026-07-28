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
        if (Schema::hasTable('capacity_ledger_entries')) {
            Schema::table('capacity_ledger_entries', function (Blueprint $table): void {
                if (! Schema::hasColumn('capacity_ledger_entries', 'wait_time')) {
                    $table->decimal('wait_time', 18, 8)->default(0)->after('run_time');
                }

                if (! Schema::hasColumn('capacity_ledger_entries', 'queue_time')) {
                    $table->decimal('queue_time', 18, 8)->default(0)->after('wait_time');
                }

                if (! Schema::hasColumn('capacity_ledger_entries', 'cost_state')) {
                    $table->string('cost_state', 30)->default('actual')->after('type');
                }

                if (! Schema::hasColumn('capacity_ledger_entries', 'idempotency_key')) {
                    $table->string('idempotency_key', 128)->nullable()->after('cost_state');
                }

                if (! Schema::hasColumn('capacity_ledger_entries', 'reversal_of_capacity_ledger_entry_id')) {
                    $table->foreignId('reversal_of_capacity_ledger_entry_id')
                        ->nullable()
                        ->after('idempotency_key')
                        ->constrained('capacity_ledger_entries')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('capacity_ledger_entries', 'costing_metadata')) {
                    $table->json('costing_metadata')->nullable()->after('reversal_of_capacity_ledger_entry_id');
                }
            });

            Schema::table('capacity_ledger_entries', function (Blueprint $table): void {
                if (! Schema::hasIndex('capacity_ledger_entries', 'capacity_ledger_entries_idempotency_key_unique')) {
                    $table->unique(['idempotency_key'], 'capacity_ledger_entries_idempotency_key_unique');
                }

                if (! Schema::hasIndex('capacity_ledger_entries', 'capacity_ledger_entries_phase_1d_costing_index')) {
                    $table->index(['production_order_id', 'routing_line_id', 'cost_state'], 'capacity_ledger_entries_phase_1d_costing_index');
                }
            });
        }

        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('production_orders', 'cost_settled_at')) {
                    $table->timestamp('cost_settled_at')->nullable()->after('posted_by');
                }

                if (! Schema::hasColumn('production_orders', 'cost_settled_by')) {
                    $table->foreignId('cost_settled_by')->nullable()->after('cost_settled_at')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('production_orders', 'cost_settlement_key')) {
                    $table->string('cost_settlement_key', 128)->nullable()->after('cost_settled_by');
                }

                if (! Schema::hasColumn('production_orders', 'cost_settlement_status')) {
                    $table->string('cost_settlement_status', 30)->default('pending')->after('cost_settlement_key');
                }

                if (! Schema::hasColumn('production_orders', 'cost_settlement_classification')) {
                    $table->string('cost_settlement_classification', 50)->nullable()->after('cost_settlement_status');
                }
            });

            Schema::table('production_orders', function (Blueprint $table): void {
                if (! Schema::hasIndex('production_orders', 'production_orders_cost_settlement_key_unique')) {
                    $table->unique(['cost_settlement_key'], 'production_orders_cost_settlement_key_unique');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table): void {
                if (Schema::hasIndex('production_orders', 'production_orders_cost_settlement_key_unique')) {
                    $table->dropUnique('production_orders_cost_settlement_key_unique');
                }

                foreach (['cost_settlement_classification', 'cost_settlement_status', 'cost_settlement_key', 'cost_settled_by', 'cost_settled_at'] as $column) {
                    if (Schema::hasColumn('production_orders', $column)) {
                        if ($column === 'cost_settled_by') {
                            $table->dropConstrainedForeignId($column);
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }

        if (Schema::hasTable('capacity_ledger_entries')) {
            Schema::table('capacity_ledger_entries', function (Blueprint $table): void {
                if (Schema::hasIndex('capacity_ledger_entries', 'capacity_ledger_entries_phase_1d_costing_index')) {
                    $table->dropIndex('capacity_ledger_entries_phase_1d_costing_index');
                }

                if (Schema::hasIndex('capacity_ledger_entries', 'capacity_ledger_entries_idempotency_key_unique')) {
                    $table->dropUnique('capacity_ledger_entries_idempotency_key_unique');
                }

                foreach (['costing_metadata', 'reversal_of_capacity_ledger_entry_id', 'idempotency_key', 'cost_state', 'queue_time', 'wait_time'] as $column) {
                    if (Schema::hasColumn('capacity_ledger_entries', $column)) {
                        if ($column === 'reversal_of_capacity_ledger_entry_id') {
                            $table->dropConstrainedForeignId($column);
                        } else {
                            $table->dropColumn($column);
                        }
                    }
                }
            });
        }
    }
};
