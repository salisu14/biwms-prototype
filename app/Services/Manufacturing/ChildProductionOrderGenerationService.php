<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionHierarchyNodeType;
use App\Enums\ProductionOrderOrigin;
use App\Enums\ProductionOrderStatus;
use App\Models\Item;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChildProductionOrderGenerationService
{
    /**
     * @param  array<string, ProductionHierarchyNode>  $nodesByPath
     * @return array{orders_by_path: array<string, ProductionOrder>, components_by_node_id: array<int, ProductionOrderComponent>}
     */
    public function generate(ProductionOrder $rootOrder, ProductionHierarchy $hierarchy, array $nodesByPath, ?int $userId = null): array
    {
        return DB::transaction(function () use ($rootOrder, $hierarchy, $nodesByPath, $userId): array {
            $ordersByPath = ['1' => $rootOrder->fresh()];
            $componentsByNodeId = [];

            foreach ($nodesByPath as $path => $node) {
                $path = (string) $path;

                if ($path === '1') {
                    continue;
                }

                $parentOrderPath = (string) data_get($node->metadata, 'parent_order_path', '1');
                $parentOrder = $ordersByPath[$parentOrderPath] ?? null;

                if (! $parentOrder) {
                    throw new RuntimeException("Parent production order for hierarchy path {$path} has not been generated.");
                }

                $component = $this->syncComponent($parentOrder, $node, $userId);
                $componentsByNodeId[$node->id] = $component;

                if ($node->node_type !== ProductionHierarchyNodeType::ManufacturedComponent) {
                    continue;
                }

                $childOrder = $this->syncChildOrder($rootOrder, $hierarchy, $node, $component, $userId);
                $ordersByPath[$path] = $childOrder;

                $node->forceFill([
                    'production_order_id' => $childOrder->id,
                    'source_production_order_component_id' => $component->id,
                ])->save();
            }

            return [
                'orders_by_path' => $ordersByPath,
                'components_by_node_id' => $componentsByNodeId,
            ];
        });
    }

    private function syncComponent(ProductionOrder $productionOrder, ProductionHierarchyNode $node, ?int $userId): ProductionOrderComponent
    {
        $lineMetadata = (array) data_get($node->metadata, 'line', []);
        $lineNumber = ((int) $node->level * 100000) + ((int) data_get($lineMetadata, 'source_line_number', $node->id));
        $quantityBase = DecimalMath::quantity($node->required_quantity_base);
        $quantityPer = DecimalMath::quantity(data_get($lineMetadata, 'quantity_per', $quantityBase));

        /** @var ProductionOrderComponent $component */
        $component = ProductionOrderComponent::query()->firstOrNew([
            'hierarchy_node_id' => $node->id,
        ]);

        $component->fill([
            'production_order_id' => $productionOrder->id,
            'line_number' => $lineNumber,
            'item_id' => $node->item_id,
            'description' => $node->description,
            'unit_of_measure_code' => $node->unit_of_measure_code,
            'quantity_per' => $quantityPer,
            'expected_quantity' => $quantityBase,
            'expected_quantity_base' => $quantityBase,
            'remaining_quantity' => $quantityBase,
            'scrap_percent' => data_get($lineMetadata, 'scrap_percent', '0'),
            'flushing_method' => data_get($lineMetadata, 'flushing_method', 'MANUAL'),
            'routing_link_code' => data_get($lineMetadata, 'routing_link_code'),
            'location_code' => data_get($lineMetadata, 'location_code') ?: $productionOrder->location_code,
            'bin_code' => data_get($lineMetadata, 'bin_code'),
            'bom_level' => $node->level,
            'bom_path' => $node->node_path,
            'source_bom_code' => data_get($lineMetadata, 'source_bom_code'),
            'is_manufactured_requirement' => $node->node_type === ProductionHierarchyNodeType::ManufacturedComponent,
            'required_supply_quantity_base' => $node->node_type === ProductionHierarchyNodeType::ManufacturedComponent ? $quantityBase : null,
        ]);

        if (! $component->exists) {
            $component->actual_quantity_consumed = '0';
            $component->actual_scrap_quantity = '0';
            $component->reserved_quantity = '0';
            $component->unit_cost = '0';
            $component->total_cost = '0';
        }

        $component->save();

        $node->forceFill(['source_production_order_component_id' => $component->id])->save();

        return $component->fresh();
    }

    private function syncChildOrder(
        ProductionOrder $rootOrder,
        ProductionHierarchy $hierarchy,
        ProductionHierarchyNode $node,
        ProductionOrderComponent $component,
        ?int $userId,
    ): ProductionOrder {
        $item = Item::query()->with(['baseUom', 'productionBom'])->findOrFail($node->item_id);
        $childBomId = data_get($node->metadata, 'child_bom_id') ?: $item->production_bom_id;

        if (! $childBomId) {
            throw new RuntimeException("Manufactured item {$item->item_code} has no production BOM.");
        }

        /** @var ProductionOrder|null $childOrder */
        $childOrder = ProductionOrder::query()
            ->where('source_production_order_component_id', $component->id)
            ->where('order_origin', ProductionOrderOrigin::GeneratedChild)
            ->first();

        $attributes = [
            'status' => ProductionOrderStatus::PLANNED,
            'source_type' => $rootOrder->source_type,
            'source_id' => $rootOrder->source_id,
            'source_no' => $rootOrder->document_number,
            'description' => 'Child supply for '.$rootOrder->document_number.' / '.$node->item_no,
            'item_id' => $node->item_id,
            'quantity' => DecimalMath::quantity($node->planned_output_quantity_base),
            'unit_of_measure_code' => $node->unit_of_measure_code,
            'quantity_base' => DecimalMath::quantity($node->planned_output_quantity_base),
            'due_date' => $rootOrder->due_date,
            'starting_date_time' => $rootOrder->starting_date_time,
            'ending_date_time' => $rootOrder->ending_date_time,
            'inventory_posting_group_id' => $item->inventory_posting_group_id ?: $rootOrder->inventory_posting_group_id,
            'general_business_posting_group_id' => $rootOrder->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id ?: $rootOrder->general_product_posting_group_id,
            'production_bom_id' => $childBomId,
            'production_bom_version_id' => data_get($node->metadata, 'child_bom_version_id'),
            'routing_id' => $item->routing_id,
            'location_code' => $rootOrder->location_code,
            'bin_code' => $rootOrder->bin_code,
            'shortcut_dimension_1_code' => $rootOrder->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $rootOrder->shortcut_dimension_2_code,
            'dimension_set_id' => $rootOrder->dimension_set_id,
            'costing_method' => $item->costing_method ?: $rootOrder->costing_method,
            'unit_cost' => $item->unit_cost ?: '0',
            'parent_production_order_id' => $component->production_order_id,
            'root_production_order_id' => $rootOrder->id,
            'production_level' => $node->level,
            'hierarchy_path' => $node->node_path,
            'order_origin' => ProductionOrderOrigin::GeneratedChild,
            'source_production_order_component_id' => $component->id,
            'planning_group_id' => $rootOrder->planning_group_id,
            'hierarchy_planning_version' => $hierarchy->planning_version,
            'created_by' => $userId,
            'last_modified_by' => $userId,
        ];

        if ($childOrder) {
            if (! $childOrder->status->isEditable()) {
                throw new RuntimeException("Generated child order {$childOrder->document_number} is no longer editable.");
            }

            $childOrder->fill($attributes)->save();

            return $childOrder->fresh();
        }

        return ProductionOrder::query()->create($attributes)->fresh();
    }
}
