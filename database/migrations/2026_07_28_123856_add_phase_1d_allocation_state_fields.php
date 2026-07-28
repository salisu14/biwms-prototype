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
        if (! Schema::hasTable('production_output_cost_allocations')) {
            return;
        }

        Schema::table('production_output_cost_allocations', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_output_cost_allocations', 'allocation_status')) {
                $table->string('allocation_status', 30)->default('pending')->after('allocated_total_cost');
            }

            if (! Schema::hasColumn('production_output_cost_allocations', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('is_final_allocation');
            }

            if (! Schema::hasColumn('production_output_cost_allocations', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('finalized_at');
            }

            if (! Schema::hasColumn('production_output_cost_allocations', 'reversed_allocation_id')) {
                $table->foreignId('reversed_allocation_id')
                    ->nullable()
                    ->after('reversed_at')
                    ->constrained('production_output_cost_allocations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('production_output_cost_allocations', 'source_identity_key')) {
                $table->string('source_identity_key', 128)->nullable()->after('idempotency_key');
            }
        });

        Schema::table('production_output_cost_allocations', function (Blueprint $table): void {
            if (! Schema::hasIndex('production_output_cost_allocations', 'production_output_allocations_source_identity_key_unique')) {
                $table->unique(['source_identity_key'], 'production_output_allocations_source_identity_key_unique');
            }

            if (! Schema::hasIndex('production_output_cost_allocations', 'production_output_allocations_status_index')) {
                $table->index(['production_order_id', 'allocation_status'], 'production_output_allocations_status_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('production_output_cost_allocations')) {
            return;
        }

        Schema::table('production_output_cost_allocations', function (Blueprint $table): void {
            if (Schema::hasIndex('production_output_cost_allocations', 'production_output_allocations_status_index')) {
                $table->dropIndex('production_output_allocations_status_index');
            }

            if (Schema::hasIndex('production_output_cost_allocations', 'production_output_allocations_source_identity_key_unique')) {
                $table->dropUnique('production_output_allocations_source_identity_key_unique');
            }

            if (Schema::hasColumn('production_output_cost_allocations', 'reversed_allocation_id')) {
                $table->dropConstrainedForeignId('reversed_allocation_id');
            }

            foreach (['source_identity_key', 'reversed_at', 'finalized_at', 'allocation_status'] as $column) {
                if (Schema::hasColumn('production_output_cost_allocations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
