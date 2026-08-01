<?php

declare(strict_types=1);

namespace Database\Factories\Manufacturing;

use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\Item;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductionOrderSupplyLink>
 */
class ProductionOrderSupplyLinkFactory extends Factory
{
    protected $model = ProductionOrderSupplyLink::class;

    public function definition(): array
    {
        $node = ProductionHierarchyNode::factory()->create();
        $component = ProductionOrderComponent::query()->create([
            'production_order_id' => $node->root_production_order_id,
            'line_number' => fake()->unique()->numberBetween(1000, 9999),
            'item_id' => $node->item_id,
            'description' => $node->description,
            'unit_of_measure_code' => 'PCS',
            'quantity_per' => 1,
            'expected_quantity' => 1,
            'expected_quantity_base' => 1,
            'remaining_quantity' => 1,
            'hierarchy_node_id' => $node->id,
            'is_manufactured_requirement' => true,
            'required_supply_quantity_base' => 1,
        ]);
        $itemId = $node->item_id ?? Item::factory()->create()->id;

        return [
            'business_id' => $node->business_id,
            'production_hierarchy_id' => $node->production_hierarchy_id,
            'production_hierarchy_node_id' => $node->id,
            'root_production_order_id' => $node->root_production_order_id,
            'parent_production_order_id' => $node->root_production_order_id,
            'parent_component_id' => $component->id,
            'item_id' => $itemId,
            'unit_of_measure_code' => 'PCS',
            'supply_type' => ProductionSupplyType::ExistingInventory,
            'status' => ProductionSupplyLinkStatus::Planned,
            'required_quantity_base' => '1',
            'planned_supply_quantity_base' => '1',
            'produced_quantity_base' => '0',
            'supplied_quantity_base' => '0',
            'consumed_quantity_base' => '0',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ];
    }
}
