<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionIntermediateHandoffStatus;
use App\Enums\ProductionOperationDependencyStatus;
use App\Enums\ProductionOperationDependencyType;
use App\Enums\ProductionSupplyType;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionIntermediateHandoff;
use App\Models\Manufacturing\ProductionOperationDependency;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionOperationDependencyGenerationService
{
    /**
     * @return array<int, ProductionOperationDependency>
     */
    public function generateForHierarchy(ProductionHierarchy $hierarchy, ?int $userId = null): array
    {
        return DB::transaction(function () use ($hierarchy, $userId): array {
            /** @var ProductionHierarchy $lockedHierarchy */
            $lockedHierarchy = ProductionHierarchy::query()
                ->with('rootProductionOrder')
                ->lockForUpdate()
                ->findOrFail($hierarchy->id);

            $links = ProductionOrderSupplyLink::query()
                ->with(['childProductionOrder.routingLines', 'parentProductionOrder.routingLines', 'parentComponent.routingLine'])
                ->where('production_hierarchy_id', $lockedHierarchy->id)
                ->where('supply_type', ProductionSupplyType::GeneratedChildOrder)
                ->whereNotNull('child_production_order_id')
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $dependencies = [];
            foreach ($links as $index => $link) {
                $dependency = $this->generateForSupplyLink($link, $userId, ($index + 1) * 10000);
                $dependencies[] = $dependency;
            }

            $this->assertAcyclic($lockedHierarchy);

            return $dependencies;
        });
    }

    public function generateForSupplyLink(ProductionOrderSupplyLink $link, ?int $userId = null, int $sequence = 10000): ProductionOperationDependency
    {
        $link->loadMissing(['childProductionOrder.routingLines', 'parentProductionOrder.routingLines', 'parentComponent.routingLine']);

        if (! $link->childProductionOrder || ! $link->parentProductionOrder) {
            throw new RuntimeException('Production supply link must have parent and child production orders before dependency generation.');
        }

        $upstreamOperation = $this->lastRoutingLine($link->childProductionOrder);
        $downstreamOperation = $this->componentRoutingLine($link) ?? $this->firstRoutingLine($link->parentProductionOrder);

        if (! $upstreamOperation || ! $downstreamOperation) {
            throw new RuntimeException('Cannot generate operation dependency without upstream and downstream routing operations.');
        }

        if ((int) $upstreamOperation->id === (int) $downstreamOperation->id) {
            throw new RuntimeException('Production operation dependency cannot reference the same routing line on both sides.');
        }

        $idempotencyKey = $this->idempotencyKey($link, $upstreamOperation, $downstreamOperation);

        $dependency = ProductionOperationDependency::query()->updateOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'business_id' => $link->business_id,
                'production_hierarchy_id' => $link->production_hierarchy_id,
                'root_production_order_id' => $link->root_production_order_id,
                'upstream_production_order_id' => $link->child_production_order_id,
                'upstream_routing_line_id' => $upstreamOperation->id,
                'downstream_production_order_id' => $link->parent_production_order_id,
                'downstream_routing_line_id' => $downstreamOperation->id,
                'production_order_supply_link_id' => $link->id,
                'item_id' => $link->item_id,
                'dependency_type' => ProductionOperationDependencyType::OutputAvailableToStart,
                'status' => ProductionOperationDependencyStatus::Planned,
                'required_quantity_base' => $link->required_quantity_base,
                'minimum_start_quantity_base' => $link->required_quantity_base,
                'fulfilled_quantity_base' => $link->supplied_quantity_base,
                'sequence' => $sequence,
                'source' => 'phase_2b_supply_link',
                'metadata' => [
                    'phase_2b' => true,
                    'mapping' => $link->parentComponent?->routing_link_code
                        ? 'component_routing_link_code'
                        : 'fallback_parent_first_operation',
                    'limitation' => $link->parentComponent?->routing_link_code
                        ? null
                        : 'Production components are not operation-mapped; dependency uses parent first executable operation.',
                ],
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );

        $this->syncHandoff($dependency->fresh(), $link, $upstreamOperation, $downstreamOperation, $userId);

        return $dependency->fresh();
    }

    public function assertAcyclic(ProductionHierarchy $hierarchy): void
    {
        $dependencies = ProductionOperationDependency::query()
            ->where('production_hierarchy_id', $hierarchy->id)
            ->whereNotIn('status', [
                ProductionOperationDependencyStatus::Cancelled->value,
                ProductionOperationDependencyStatus::Invalid->value,
            ])
            ->get(['upstream_routing_line_id', 'downstream_routing_line_id']);

        $graph = [];
        foreach ($dependencies as $dependency) {
            if (! $dependency->upstream_routing_line_id || ! $dependency->downstream_routing_line_id) {
                continue;
            }

            $graph[(int) $dependency->upstream_routing_line_id][] = (int) $dependency->downstream_routing_line_id;
        }

        $visiting = [];
        $visited = [];

        $visit = function (int $node) use (&$visit, &$visiting, &$visited, $graph): void {
            if (($visiting[$node] ?? false) === true) {
                throw new RuntimeException('Production operation dependency graph contains a cycle.');
            }

            if (($visited[$node] ?? false) === true) {
                return;
            }

            $visiting[$node] = true;
            foreach ($graph[$node] ?? [] as $next) {
                $visit($next);
            }

            unset($visiting[$node]);
            $visited[$node] = true;
        };

        foreach (array_keys($graph) as $node) {
            $visit((int) $node);
        }
    }

    private function syncHandoff(
        ProductionOperationDependency $dependency,
        ProductionOrderSupplyLink $link,
        ProductionOrderRoutingLine $upstreamOperation,
        ProductionOrderRoutingLine $downstreamOperation,
        ?int $userId,
    ): void {
        ProductionIntermediateHandoff::query()->updateOrCreate(
            ['idempotency_key' => 'handoff:'.$dependency->idempotency_key],
            [
                'business_id' => $link->business_id,
                'production_hierarchy_id' => $link->production_hierarchy_id,
                'production_operation_dependency_id' => $dependency->id,
                'production_order_supply_link_id' => $link->id,
                'production_material_reservation_id' => $link->materialReservations()->value('id'),
                'source_production_order_id' => $link->child_production_order_id,
                'source_routing_line_id' => $upstreamOperation->id,
                'destination_production_order_id' => $link->parent_production_order_id,
                'destination_routing_line_id' => $downstreamOperation->id,
                'item_id' => $link->item_id,
                'child_output_item_ledger_entry_id' => $link->materialReservations()->value('child_output_item_ledger_entry_id'),
                'quantity_required_base' => $link->required_quantity_base,
                'quantity_available_base' => $link->supplied_quantity_base,
                'quantity_transferred_base' => $link->consumed_quantity_base,
                'status' => ProductionIntermediateHandoffStatus::Planned,
                'last_synced_at' => now(),
                'metadata' => ['phase_2b' => true, 'source' => 'production_supply_link'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }

    private function firstRoutingLine(ProductionOrder $productionOrder): ?ProductionOrderRoutingLine
    {
        return $productionOrder->routingLines()->orderBy('line_number')->first();
    }

    private function lastRoutingLine(ProductionOrder $productionOrder): ?ProductionOrderRoutingLine
    {
        return $productionOrder->routingLines()->orderByDesc('line_number')->first();
    }

    private function componentRoutingLine(ProductionOrderSupplyLink $link): ?ProductionOrderRoutingLine
    {
        return $link->parentComponent?->routingLine;
    }

    private function idempotencyKey(ProductionOrderSupplyLink $link, ProductionOrderRoutingLine $upstreamOperation, ProductionOrderRoutingLine $downstreamOperation): string
    {
        return hash('sha256', implode('|', [
            'phase-2b-operation-dependency',
            $link->id,
            $upstreamOperation->id,
            $downstreamOperation->id,
            ProductionOperationDependencyType::OutputAvailableToStart->value,
        ]));
    }
}
