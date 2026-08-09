<?php

declare(strict_types=1);

use App\Enums\ProductionOperationDependencyStatus;
use App\Enums\ProductionOperationDependencyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_operation_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('production_hierarchy_id')->nullable()->constrained('production_hierarchies')->restrictOnDelete();
            $table->foreignId('root_production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('upstream_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('upstream_routing_line_id')->nullable()->constrained('production_order_routing_lines')->restrictOnDelete();
            $table->foreignId('downstream_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('downstream_routing_line_id')->nullable()->constrained('production_order_routing_lines')->restrictOnDelete();
            $table->foreignId('production_order_supply_link_id')->nullable()->constrained('production_order_supply_links')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('dependency_type', 60)->default(ProductionOperationDependencyType::OutputAvailableToStart->value);
            $table->string('status', 40)->default(ProductionOperationDependencyStatus::Planned->value);
            $table->decimal('required_quantity_base', 15, 8)->default(0);
            $table->decimal('minimum_start_quantity_base', 15, 8)->default(0);
            $table->decimal('fulfilled_quantity_base', 15, 8)->default(0);
            $table->unsignedInteger('sequence')->default(10000);
            $table->string('source', 80)->default('phase_2b_dependency_generation');
            $table->string('idempotency_key')->unique();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['production_hierarchy_id', 'status'], 'prod_op_deps_hierarchy_status_idx');
            $table->index(['upstream_production_order_id', 'upstream_routing_line_id'], 'prod_op_deps_upstream_idx');
            $table->index(['downstream_production_order_id', 'downstream_routing_line_id'], 'prod_op_deps_downstream_idx');
            $table->index(['production_order_supply_link_id', 'status'], 'prod_op_deps_supply_status_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table production_operation_dependencies add constraint prod_op_deps_quantity_check check (required_quantity_base >= 0 and minimum_start_quantity_base >= 0 and fulfilled_quantity_base >= 0)');
            DB::statement("create unique index prod_op_deps_active_unique on production_operation_dependencies (upstream_production_order_id, coalesce(upstream_routing_line_id, 0), downstream_production_order_id, coalesce(downstream_routing_line_id, 0), dependency_type) where status not in ('cancelled', 'invalid')");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists prod_op_deps_active_unique');
        }

        Schema::dropIfExists('production_operation_dependencies');
    }
};
