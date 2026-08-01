<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_components', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_order_components', 'hierarchy_node_id')) {
                $table->foreignId('hierarchy_node_id')
                    ->nullable()
                    ->constrained('production_hierarchy_nodes')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('production_order_components', 'is_manufactured_requirement')) {
                $table->boolean('is_manufactured_requirement')->default(false);
            }

            if (! Schema::hasColumn('production_order_components', 'required_supply_quantity_base')) {
                $table->decimal('required_supply_quantity_base', 15, 8)->nullable();
            }
        });

        Schema::table('production_order_components', function (Blueprint $table): void {
            $table->index('hierarchy_node_id', 'production_order_components_hierarchy_node_idx');
            $table->index(['production_order_id', 'is_manufactured_requirement'], 'production_order_components_mfg_req_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table production_order_components add constraint production_order_components_required_supply_quantity_check check (required_supply_quantity_base is null or required_supply_quantity_base >= 0)');
        }
    }

    public function down(): void
    {
        Schema::table('production_order_components', function (Blueprint $table): void {
            $table->dropIndex('production_order_components_hierarchy_node_idx');
            $table->dropIndex('production_order_components_mfg_req_idx');
            $table->dropConstrainedForeignId('hierarchy_node_id');
            $table->dropColumn(['is_manufactured_requirement', 'required_supply_quantity_base']);
        });
    }
};
