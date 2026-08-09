<?php

declare(strict_types=1);

use App\Enums\ProductionIntermediateHandoffStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_intermediate_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('production_hierarchy_id')->nullable()->constrained('production_hierarchies')->restrictOnDelete();
            $table->foreignId('production_operation_dependency_id')->nullable()->constrained('production_operation_dependencies')->nullOnDelete();
            $table->foreignId('production_order_supply_link_id')->nullable()->constrained('production_order_supply_links')->nullOnDelete();
            $table->foreignId('production_material_reservation_id')->nullable()->constrained('production_material_reservations')->nullOnDelete();
            $table->foreignId('source_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('source_routing_line_id')->nullable()->constrained('production_order_routing_lines')->restrictOnDelete();
            $table->foreignId('destination_production_order_id')->constrained('production_orders')->restrictOnDelete();
            $table->foreignId('destination_routing_line_id')->nullable()->constrained('production_order_routing_lines')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('child_output_item_ledger_entry_id')->nullable()->constrained('item_ledger_entries')->restrictOnDelete();
            $table->string('lot_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('quantity_required_base', 15, 8)->default(0);
            $table->decimal('quantity_available_base', 15, 8)->default(0);
            $table->decimal('quantity_transferred_base', 15, 8)->default(0);
            $table->string('status', 40)->default(ProductionIntermediateHandoffStatus::Planned->value);
            $table->string('quality_status', 40)->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['production_hierarchy_id', 'status'], 'prod_handoffs_hierarchy_status_idx');
            $table->index(['source_production_order_id', 'source_routing_line_id'], 'prod_handoffs_source_idx');
            $table->index(['destination_production_order_id', 'destination_routing_line_id'], 'prod_handoffs_destination_idx');
            $table->index(['item_id', 'status'], 'prod_handoffs_item_status_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table production_intermediate_handoffs add constraint prod_handoffs_quantity_check check (quantity_required_base >= 0 and quantity_available_base >= 0 and quantity_transferred_base >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_intermediate_handoffs');
    }
};
