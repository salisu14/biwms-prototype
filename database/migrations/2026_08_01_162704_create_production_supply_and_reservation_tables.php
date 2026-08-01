<?php

use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_supply_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('production_hierarchy_id')->nullable()->constrained('production_hierarchies')->restrictOnDelete();
            $table->foreignId('production_hierarchy_node_id')->nullable()->constrained('production_hierarchy_nodes')->restrictOnDelete();
            $table->foreignId('root_production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('parent_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('parent_component_id')->constrained('production_order_components')->restrictOnDelete();
            $table->foreignId('child_production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->string('unit_of_measure_code', 20)->nullable();
            $table->string('supply_type', 40)->default(ProductionSupplyType::ExistingInventory->value);
            $table->string('status', 40)->default(ProductionSupplyLinkStatus::Planned->value);
            $table->decimal('required_quantity_base', 15, 8)->default(0);
            $table->decimal('planned_supply_quantity_base', 15, 8)->default(0);
            $table->decimal('produced_quantity_base', 15, 8)->default(0);
            $table->decimal('supplied_quantity_base', 15, 8)->default(0);
            $table->decimal('consumed_quantity_base', 15, 8)->default(0);
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['parent_production_order_id', 'parent_component_id'], 'production_supply_links_parent_component_idx');
            $table->index(['child_production_order_id', 'status'], 'production_supply_links_child_status_idx');
            $table->index(['item_id', 'status'], 'production_supply_links_item_status_idx');
        });

        Schema::create('production_material_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('production_hierarchy_id')->nullable()->constrained('production_hierarchies')->restrictOnDelete();
            $table->foreignId('production_hierarchy_node_id')->nullable()->constrained('production_hierarchy_nodes')->restrictOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('production_order_component_id')->constrained('production_order_components')->restrictOnDelete();
            $table->foreignId('production_order_supply_link_id')->nullable()->constrained('production_order_supply_links')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('bin_code', 20)->nullable();
            $table->foreignId('item_ledger_entry_id')->nullable()->constrained('item_ledger_entries')->restrictOnDelete();
            $table->foreignId('child_output_item_ledger_entry_id')->nullable()->constrained('item_ledger_entries')->restrictOnDelete();
            $table->foreignId('child_production_order_id')->nullable()->constrained('production_orders')->restrictOnDelete();
            $table->string('reservation_type', 40)->default(ProductionReservationType::ExistingInventory->value);
            $table->string('status', 40)->default(ProductionReservationStatus::Active->value);
            $table->decimal('quantity_base', 15, 8);
            $table->decimal('remaining_quantity_base', 15, 8)->default(0);
            $table->date('reserved_until')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'status'], 'production_reservations_order_status_idx');
            $table->index(['production_order_component_id', 'status'], 'production_reservations_component_status_idx');
            $table->index(['item_id', 'location_id', 'status'], 'production_reservations_item_location_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table production_order_supply_links add constraint production_supply_links_quantity_check check (required_quantity_base >= 0 and planned_supply_quantity_base >= 0 and produced_quantity_base >= 0 and supplied_quantity_base >= 0 and consumed_quantity_base >= 0)');
            DB::statement("create unique index production_supply_links_active_child_unique on production_order_supply_links (parent_component_id, child_production_order_id, supply_type) where child_production_order_id is not null and status not in ('cancelled', 'exception')");
            DB::statement('alter table production_material_reservations add constraint production_reservations_quantity_check check (quantity_base > 0 and remaining_quantity_base >= 0 and remaining_quantity_base <= quantity_base)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('drop index if exists production_supply_links_active_child_unique');
        }

        Schema::dropIfExists('production_material_reservations');
        Schema::dropIfExists('production_order_supply_links');
    }
};
