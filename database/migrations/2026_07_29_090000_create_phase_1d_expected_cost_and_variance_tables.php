<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_expected_cost_snapshots')) {
            Schema::create('production_expected_cost_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
                $table->foreignId('finished_item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->foreignId('production_bom_id')->nullable()->constrained('production_boms')->nullOnDelete();
                $table->foreignId('production_bom_version_id')->nullable()->constrained('production_bom_versions')->nullOnDelete();
                $table->foreignId('routing_id')->nullable()->constrained('routings')->nullOnDelete();
                $table->foreignId('routing_version_id')->nullable()->constrained('routing_versions')->nullOnDelete();
                $table->decimal('production_quantity_base', 24, 8)->default(0);
                $table->date('costing_date');
                $table->decimal('expected_material_cost', 24, 4)->default(0);
                $table->decimal('expected_capacity_cost', 24, 4)->default(0);
                $table->decimal('expected_overhead_cost', 24, 4)->default(0);
                $table->decimal('expected_output_cost', 24, 4)->default(0);
                $table->decimal('expected_total_cost', 24, 4)->default(0);
                $table->string('calculation_identity', 128)->unique();
                $table->string('status', 30)->default('calculated');
                $table->json('component_details')->nullable();
                $table->json('routing_details')->nullable();
                $table->json('cost_source_details')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->index(['production_order_id', 'costing_date'], 'production_expected_cost_order_date_index');
                $table->index(['production_order_id', 'status'], 'production_expected_cost_order_status_index');
            });
        }

        if (! Schema::hasTable('production_variance_calculations')) {
            Schema::create('production_variance_calculations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
                $table->foreignId('production_expected_cost_snapshot_id')->nullable()->constrained('production_expected_cost_snapshots')->nullOnDelete();
                $table->string('variance_type', 50);
                $table->string('cost_component', 50);
                $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
                $table->foreignId('production_order_component_id')->nullable()->constrained('production_order_components')->nullOnDelete();
                $table->foreignId('production_order_routing_line_id')->nullable()->constrained('production_order_routing_lines')->nullOnDelete();
                $table->foreignId('capacity_ledger_entry_id')->nullable()->constrained('capacity_ledger_entries')->nullOnDelete();
                $table->decimal('expected_quantity', 24, 8)->default(0);
                $table->decimal('actual_quantity', 24, 8)->default(0);
                $table->decimal('expected_rate', 24, 8)->default(0);
                $table->decimal('actual_rate', 24, 8)->default(0);
                $table->decimal('expected_amount', 24, 4)->default(0);
                $table->decimal('actual_amount', 24, 4)->default(0);
                $table->decimal('variance_amount', 24, 4)->default(0);
                $table->string('variance_reason')->nullable();
                $table->string('calculation_identity', 128)->unique();
                $table->string('settlement_identity', 128)->nullable();
                $table->date('posting_date');
                $table->date('original_source_date')->nullable();
                $table->foreignId('posted_value_entry_id')->nullable()->constrained('value_entries')->nullOnDelete();
                $table->foreignId('reversal_of_variance_calculation_id')->nullable()->constrained('production_variance_calculations')->nullOnDelete();
                $table->foreignId('cost_adjustment_batch_id')->nullable()->constrained('cost_adjustment_batches')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->index(['production_order_id', 'variance_type'], 'production_variances_order_type_index');
                $table->index(['production_order_id', 'posted_value_entry_id'], 'production_variances_order_posted_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_variance_calculations');
        Schema::dropIfExists('production_expected_cost_snapshots');
    }
};
