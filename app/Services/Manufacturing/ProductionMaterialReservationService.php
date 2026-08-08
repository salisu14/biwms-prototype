<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionHierarchyNodeType;
use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionMaterialReservationService
{
    /**
     * @param  array<string, ProductionHierarchyNode>  $nodesByPath
     * @param  array<int, ProductionOrderComponent>  $componentsByNodeId
     * @param  array<string, ProductionOrder>  $ordersByPath
     */
    public function reserveGeneratedChildSupply(
        ProductionOrder $rootOrder,
        ProductionHierarchy $hierarchy,
        array $nodesByPath,
        array $componentsByNodeId,
        array $ordersByPath,
        ?int $userId = null,
    ): void {
        DB::transaction(function () use ($rootOrder, $hierarchy, $nodesByPath, $componentsByNodeId, $ordersByPath, $userId): void {
            $activeLinkIds = [];
            $activeReservationIds = [];

            foreach ($nodesByPath as $path => $node) {
                if ($path === '1' || $node->node_type !== ProductionHierarchyNodeType::ManufacturedComponent) {
                    continue;
                }

                $component = $componentsByNodeId[$node->id] ?? null;
                $childOrder = $ordersByPath[$path] ?? null;

                if (! $component || ! $childOrder) {
                    continue;
                }

                $link = $this->syncSupplyLink($rootOrder, $hierarchy, $node, $component, $childOrder, $userId);
                $reservation = $this->syncReservation($hierarchy, $node, $component, $link, $childOrder, $userId);

                $activeLinkIds[] = $link->id;
                $activeReservationIds[] = $reservation->id;

                $component->forceFill(['reserved_quantity' => DecimalMath::quantity($reservation->quantity_base)])->save();
                $node->forceFill(['reserved_quantity_base' => DecimalMath::quantity($reservation->quantity_base)])->save();
            }

            $this->cancelStaleGeneratedChildSupply($hierarchy, $activeLinkIds, $activeReservationIds, $userId);
        });
    }

    private function syncSupplyLink(
        ProductionOrder $rootOrder,
        ProductionHierarchy $hierarchy,
        ProductionHierarchyNode $node,
        ProductionOrderComponent $component,
        ProductionOrder $childOrder,
        ?int $userId,
    ): ProductionOrderSupplyLink {
        return ProductionOrderSupplyLink::allowServiceMutation(function () use ($rootOrder, $hierarchy, $node, $component, $childOrder, $userId): ProductionOrderSupplyLink {
            $quantityBase = DecimalMath::quantity($node->planned_output_quantity_base);

            /** @var ProductionOrderSupplyLink $link */
            $link = ProductionOrderSupplyLink::query()->firstOrNew([
                'idempotency_key' => $this->idempotencyKey('supply-link', $rootOrder, $node),
            ]);

            $link->fill([
                'business_id' => $hierarchy->business_id,
                'production_hierarchy_id' => $hierarchy->id,
                'production_hierarchy_node_id' => $node->id,
                'root_production_order_id' => $rootOrder->id,
                'parent_production_order_id' => $component->production_order_id,
                'parent_component_id' => $component->id,
                'child_production_order_id' => $childOrder->id,
                'item_id' => $node->item_id,
                'unit_of_measure_code' => $node->unit_of_measure_code,
                'supply_type' => ProductionSupplyType::GeneratedChildOrder,
                'status' => ProductionSupplyLinkStatus::ChildOrderCreated,
                'required_quantity_base' => $quantityBase,
                'planned_supply_quantity_base' => $quantityBase,
                'created_by' => $link->exists ? $link->created_by : $userId,
                'updated_by' => $userId,
                'metadata' => [
                    'phase' => '2a.2',
                    'child_order_no' => $childOrder->document_number,
                ],
            ])->save();

            return $link->fresh();
        });
    }

    private function syncReservation(
        ProductionHierarchy $hierarchy,
        ProductionHierarchyNode $node,
        ProductionOrderComponent $component,
        ProductionOrderSupplyLink $link,
        ProductionOrder $childOrder,
        ?int $userId,
    ): ProductionMaterialReservation {
        return ProductionMaterialReservation::allowServiceMutation(function () use ($hierarchy, $node, $component, $link, $childOrder, $userId): ProductionMaterialReservation {
            $quantityBase = DecimalMath::quantity($node->planned_output_quantity_base);

            /** @var ProductionMaterialReservation $reservation */
            $reservation = ProductionMaterialReservation::query()->firstOrNew([
                'idempotency_key' => $this->idempotencyKey('reservation', $childOrder, $node),
            ]);

            $reservation->fill([
                'business_id' => $hierarchy->business_id,
                'production_hierarchy_id' => $hierarchy->id,
                'production_hierarchy_node_id' => $node->id,
                'production_order_id' => $component->production_order_id,
                'production_order_component_id' => $component->id,
                'production_order_supply_link_id' => $link->id,
                'item_id' => $node->item_id,
                'child_production_order_id' => $childOrder->id,
                'reservation_type' => ProductionReservationType::ChildOutput,
                'status' => ProductionReservationStatus::Active,
                'quantity_base' => $quantityBase,
                'remaining_quantity_base' => $quantityBase,
                'created_by' => $reservation->exists ? $reservation->created_by : $userId,
                'updated_by' => $userId,
                'metadata' => [
                    'phase' => '2a.2',
                    'child_order_no' => $childOrder->document_number,
                    'source' => 'generated_child_output',
                ],
            ])->save();

            return $reservation->fresh();
        });
    }

    /**
     * @param  array<int, int>  $activeLinkIds
     * @param  array<int, int>  $activeReservationIds
     */
    private function cancelStaleGeneratedChildSupply(ProductionHierarchy $hierarchy, array $activeLinkIds, array $activeReservationIds, ?int $userId): void
    {
        ProductionMaterialReservation::allowServiceMutation(function () use ($hierarchy, $activeReservationIds, $userId): void {
            ProductionMaterialReservation::query()
                ->where('production_hierarchy_id', $hierarchy->id)
                ->where('reservation_type', ProductionReservationType::ChildOutput)
                ->when($activeReservationIds !== [], fn ($query) => $query->whereNotIn('id', $activeReservationIds))
                ->update([
                    'status' => ProductionReservationStatus::Cancelled->value,
                    'released_at' => now(),
                    'updated_by' => $userId,
                ]);
        });

        ProductionOrderSupplyLink::allowServiceMutation(function () use ($hierarchy, $activeLinkIds, $userId): void {
            ProductionOrderSupplyLink::query()
                ->where('production_hierarchy_id', $hierarchy->id)
                ->where('supply_type', ProductionSupplyType::GeneratedChildOrder)
                ->when($activeLinkIds !== [], fn ($query) => $query->whereNotIn('id', $activeLinkIds))
                ->update([
                    'status' => ProductionSupplyLinkStatus::Cancelled->value,
                    'updated_by' => $userId,
                ]);
        });
    }

    private function idempotencyKey(string $type, ProductionOrder $rootOrChildOrder, ProductionHierarchyNode $node): string
    {
        return 'phase2a2:'.$type.':'.Str::of($rootOrChildOrder->id.'|'.$node->node_path.'|'.$node->item_id)->slug(':');
    }
}
