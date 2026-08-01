<?php

use App\Enums\ItemType;
use App\Enums\ProductionOrderOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'item_type') && DB::getDriverName() === 'pgsql') {
            $values = collect(ItemType::cases())
                ->map(fn (ItemType $type): string => "'{$type->value}'")
                ->implode(', ');

            DB::statement('alter table items drop constraint if exists items_item_type_check');
            DB::statement("alter table items add constraint items_item_type_check check (item_type in ({$values}))");
        }

        Schema::table('production_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('production_orders', 'parent_production_order_id')) {
                $table->foreignId('parent_production_order_id')
                    ->nullable()
                    ->constrained('production_orders')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('production_orders', 'root_production_order_id')) {
                $table->foreignId('root_production_order_id')
                    ->nullable()
                    ->constrained('production_orders')
                    ->restrictOnDelete();
            }

            if (! Schema::hasColumn('production_orders', 'production_level')) {
                $table->unsignedSmallInteger('production_level')->default(0);
            }

            if (! Schema::hasColumn('production_orders', 'hierarchy_path')) {
                $table->text('hierarchy_path')->nullable();
            }

            if (! Schema::hasColumn('production_orders', 'order_origin')) {
                $table->string('order_origin', 40)->default(ProductionOrderOrigin::Standalone->value);
            }

            if (! Schema::hasColumn('production_orders', 'source_production_order_component_id')) {
                $table->foreignId('source_production_order_component_id')
                    ->nullable()
                    ->constrained('production_order_components')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('production_orders', 'planning_group_id')) {
                $table->uuid('planning_group_id')->nullable();
            }

            if (! Schema::hasColumn('production_orders', 'hierarchy_planning_version')) {
                $table->unsignedInteger('hierarchy_planning_version')->nullable();
            }
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->index('parent_production_order_id', 'production_orders_parent_idx');
            $table->index('root_production_order_id', 'production_orders_root_idx');
            $table->index('planning_group_id', 'production_orders_planning_group_idx');
            $table->index(['root_production_order_id', 'production_level'], 'production_orders_root_level_idx');
            $table->index('order_origin', 'production_orders_order_origin_idx');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropIndex('production_orders_parent_idx');
            $table->dropIndex('production_orders_root_idx');
            $table->dropIndex('production_orders_planning_group_idx');
            $table->dropIndex('production_orders_root_level_idx');
            $table->dropIndex('production_orders_order_origin_idx');

            $table->dropConstrainedForeignId('parent_production_order_id');
            $table->dropConstrainedForeignId('root_production_order_id');
            $table->dropConstrainedForeignId('source_production_order_component_id');
            $table->dropColumn([
                'production_level',
                'hierarchy_path',
                'order_origin',
                'planning_group_id',
                'hierarchy_planning_version',
            ]);
        });
    }
};
