<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Enums\ProductionBomLineBasis;
use App\Enums\ProductionHierarchyNodeStatus;
use App\Enums\ProductionHierarchyNodeType;
use App\Models\Item;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductionHierarchyNode>
 */
class ProductionHierarchyNodeFactory extends Factory
{
    protected $model = ProductionHierarchyNode::class;

    public function definition(): array
    {
        $hierarchy = ProductionHierarchy::factory()->create();
        $item = Item::factory()->create();

        return [
            'business_id' => $hierarchy->business_id,
            'production_hierarchy_id' => $hierarchy->id,
            'root_production_order_id' => $hierarchy->root_production_order_id,
            'production_order_id' => $hierarchy->root_production_order_id,
            'node_path' => '1',
            'level' => 0,
            'node_type' => ProductionHierarchyNodeType::RootOutput,
            'status' => ProductionHierarchyNodeStatus::Planned,
            'item_id' => $item->id,
            'item_no' => $item->item_code,
            'description' => $item->description,
            'unit_of_measure_code' => 'PCS',
            'required_quantity_base' => '1',
            'remaining_required_quantity_base' => '1',
            'planned_output_quantity_base' => '1',
            'reserved_quantity_base' => '0',
            'supplied_quantity_base' => '0',
            'line_basis' => ProductionBomLineBasis::PerUnit,
            'idempotency_key' => (string) Str::uuid(),
            'snapshot' => [],
            'metadata' => [],
        ];
    }
}
