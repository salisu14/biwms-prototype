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
use App\Support\DecimalMath;
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
        $downstreamResolution = $this->resolveDownstreamOperation($link);
        $downstreamOperation = $downstreamResolution['operation'];

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
                'fulfilled_quantity_base' => $this->cappedQuantity($link->supplied_quantity_base, $link->required_quantity_base),
                'sequence' => $sequence,
                'source' => 'phase_2b_supply_link',
                'metadata' => [
                    'phase_2b' => true,
                    'mapping' => $downstreamResolution['mapping'],
                    'limitation' => $downstreamResolution['limitation'],
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
                'quantity_available_base' => $this->cappedQuantity($link->supplied_quantity_base, $link->required_quantity_base),
                'quantity_transferred_base' => $this->cappedQuantity($link->consumed_quantity_base, $link->required_quantity_base),
                'status' => ProductionIntermediateHandoffStatus::Planned,
                'last_synced_at' => now(),
                'metadata' => ['phase_2b' => true, 'source' => 'production_supply_link'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function unresolvedMappingForSupplyLink(ProductionOrderSupplyLink $link): ?array
    {
        try {
            $link->loadMissing(['parentProductionOrder.routingLines', 'parentComponent.routingLine']);
            $this->resolveDownstreamOperation($link);

            return null;
        } catch (RuntimeException $exception) {
            return [
                'classification' => 'unresolved_dependency_mapping',
                'severity' => 'critical',
                'production_hierarchy_id' => $link->production_hierarchy_id,
                'production_order_supply_link_id' => $link->id,
                'parent_production_order_id' => $link->parent_production_order_id,
                'parent_component_id' => $link->parent_component_id,
                'child_production_order_id' => $link->child_production_order_id,
                'item_id' => $link->item_id,
                'message' => 'Dependency mapping requires review.',
                'details' => $exception->getMessage(),
                'remediation' => 'Assign the parent component to a routing operation or reduce the parent order to one unambiguous operation before regenerating dependencies.',
            ];
        }
    }

    private function singleRoutingLine(ProductionOrder $productionOrder): ?ProductionOrderRoutingLine
    {
        $routingLines = $productionOrder->routingLines()
            ->orderBy('line_number')
            ->limit(2)
            ->get();

        if ($routingLines->count() === 1) {
            return $routingLines->first();
        }

        if ($routingLines->count() === 0) {
            throw new RuntimeException('Dependency mapping requires review: parent production order has no routing operations.');
        }

        throw new RuntimeException('Dependency mapping requires review: parent component is not mapped to a routing operation and the parent production order has multiple possible downstream operations.');
    }

    private function lastRoutingLine(ProductionOrder $productionOrder): ?ProductionOrderRoutingLine
    {
        return $productionOrder->routingLines()->orderByDesc('line_number')->first();
    }

    private function componentRoutingLine(ProductionOrderSupplyLink $link): ?ProductionOrderRoutingLine
    {
        $component = $link->parentComponent;
        if (! $component) {
            return null;
        }

        $routingLinkCode = trim((string) $component->routing_link_code);
        if ($routingLinkCode === '') {
            return null;
        }

        $routingLine = ProductionOrderRoutingLine::query()
            ->where('production_order_id', $component->production_order_id)
            ->where('routing_link_code', $routingLinkCode)
            ->orderBy('line_number')
            ->first();
        if (! $routingLine) {
            throw new RuntimeException("Dependency mapping requires review: parent component routing link code [{$routingLinkCode}] does not match a parent routing operation.");
        }

        return $routingLine;
    }

    /**
     * @return array{operation: ProductionOrderRoutingLine, mapping: string, limitation: ?string}
     */
    private function resolveDownstreamOperation(ProductionOrderSupplyLink $link): array
    {
        $componentOperation = $this->componentRoutingLine($link);
        if ($componentOperation) {
            return [
                'operation' => $componentOperation,
                'mapping' => 'component_routing_link_code',
                'limitation' => null,
            ];
        }

        $singleOperation = $this->singleRoutingLine($link->parentProductionOrder);

        return [
            'operation' => $singleOperation,
            'mapping' => 'single_parent_operation',
            'limitation' => 'Parent component is not operation-mapped, but the parent production order has exactly one routing operation.',
        ];
    }

    private function cappedQuantity(mixed $quantityBase, mixed $requiredQuantityBase): string
    {
        $quantity = DecimalMath::quantity($quantityBase);
        $required = DecimalMath::quantity($requiredQuantityBase);

        return DecimalMath::compare($quantity, $required) > 0 ? $required : $quantity;
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
