<?php

use App\Enums\ProductionBomLineBasis;
use App\Enums\ProductionHierarchyNodeStatus;
use App\Enums\ProductionHierarchyNodeType;
use App\Enums\ProductionHierarchyReadinessClassification;
use App\Enums\ProductionHierarchyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_hierarchies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('root_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->unsignedInteger('planning_version')->default(1);
            $table->string('status', 40)->default(ProductionHierarchyStatus::Draft->value);
            $table->string('readiness_classification', 40)->default(ProductionHierarchyReadinessClassification::Ready->value);
            $table->unsignedSmallInteger('max_depth')->default(25);
            $table->unsignedInteger('node_count')->default(0);
            $table->unsignedInteger('manufactured_component_count')->default(0);
            $table->decimal('planned_quantity_base', 15, 8)->default(0);
            $table->string('planned_uom_code', 20)->nullable();
            $table->foreignId('supersedes_hierarchy_id')->nullable()->constrained('production_hierarchies')->nullOnDelete();
            $table->foreignId('superseded_by_hierarchy_id')->nullable()->constrained('production_hierarchies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['root_production_order_id', 'planning_version'], 'production_hierarchies_root_version_unique');
            $table->index(['business_id', 'status'], 'production_hierarchies_business_status_idx');
            $table->index(['root_production_order_id', 'status'], 'production_hierarchies_root_status_idx');
        });

        Schema::create('production_hierarchy_nodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('production_hierarchy_id')->constrained('production_hierarchies')->restrictOnDelete();
            $table->foreignId('root_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('parent_node_id')->nullable()->constrained('production_hierarchy_nodes')->nullOnDelete();
            $table->string('node_path')->index();
            $table->unsignedSmallInteger('level')->default(0);
            $table->string('node_type', 60)->default(ProductionHierarchyNodeType::PurchasedComponent->value);
            $table->string('status', 40)->default(ProductionHierarchyNodeStatus::Planned->value);
            $table->foreignId('item_id')->nullable()->constrained('items')->restrictOnDelete();
            $table->string('item_no')->nullable();
            $table->text('description')->nullable();
            $table->string('unit_of_measure_code', 20)->nullable();
            $table->decimal('required_quantity_base', 15, 8)->default(0);
            $table->decimal('remaining_required_quantity_base', 15, 8)->default(0);
            $table->decimal('planned_output_quantity_base', 15, 8)->default(0);
            $table->decimal('reserved_quantity_base', 15, 8)->default(0);
            $table->decimal('supplied_quantity_base', 15, 8)->default(0);
            $table->foreignId('source_bom_id')->nullable()->constrained('production_boms')->nullOnDelete();
            $table->foreignId('source_bom_version_id')->nullable()->constrained('production_bom_versions')->nullOnDelete();
            $table->foreignId('source_bom_line_id')->nullable()->constrained('production_bom_lines')->nullOnDelete();
            $table->foreignId('source_production_order_component_id')->nullable()->constrained('production_order_components')->nullOnDelete();
            $table->string('line_basis', 40)->nullable()->default(ProductionBomLineBasis::PerUnit->value);
            $table->decimal('reference_quantity', 15, 8)->nullable();
            $table->string('reference_uom_code', 20)->nullable();
            $table->decimal('reference_quantity_base', 15, 8)->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('snapshot')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['production_hierarchy_id', 'parent_node_id'], 'production_hierarchy_nodes_parent_idx');
            $table->index(['root_production_order_id', 'level'], 'production_hierarchy_nodes_root_level_idx');
            $table->index(['item_id', 'node_type'], 'production_hierarchy_nodes_item_type_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table production_hierarchies add constraint production_hierarchies_planning_version_check check (planning_version >= 1)');
            DB::statement('alter table production_hierarchies add constraint production_hierarchies_max_depth_check check (max_depth between 1 and 25)');
            DB::statement('alter table production_hierarchies add constraint production_hierarchies_quantity_check check (planned_quantity_base >= 0)');
            DB::statement("create unique index production_hierarchies_current_unique on production_hierarchies (root_production_order_id) where superseded_by_hierarchy_id is null and status in ('draft', 'planned', 'exploded', 'children_generated')");
            DB::statement('alter table production_hierarchy_nodes add constraint production_hierarchy_nodes_level_check check (level >= 0)');
            DB::statement('alter table production_hierarchy_nodes add constraint production_hierarchy_nodes_quantity_check check (required_quantity_base >= 0 and remaining_required_quantity_base >= 0 and planned_output_quantity_base >= 0 and reserved_quantity_base >= 0 and supplied_quantity_base >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists production_hierarchies_current_unique');
        }

        Schema::dropIfExists('production_hierarchy_nodes');
        Schema::dropIfExists('production_hierarchies');
    }
};
