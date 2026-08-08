<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionHierarchyNodeStatus;
use App\Enums\ProductionHierarchyNodeType;
use App\Enums\ProductionHierarchyReadinessClassification;
use App\Enums\ProductionHierarchyStatus;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionHierarchyService
{
    /**
     * @param  array{
     *     root: array<string, mixed>,
     *     nodes: array<int, array<string, mixed>>,
     *     manufactured_count: int,
     *     node_count: int,
     *     max_depth: int
     * }  $explosion
     * @return array{hierarchy: ProductionHierarchy, nodes_by_path: array<string, ProductionHierarchyNode>}
     */
    public function persist(ProductionOrder $rootOrder, array $explosion, ?int $userId = null): array
    {
        $this->assertCanPlan($rootOrder);

        return DB::transaction(function () use ($rootOrder, $explosion, $userId): array {
            /** @var ProductionOrder $lockedRoot */
            $lockedRoot = ProductionOrder::query()->lockForUpdate()->findOrFail($rootOrder->id);
            $this->assertCanPlan($lockedRoot);

            if (! $lockedRoot->planning_group_id) {
                $lockedRoot->forceFill(['planning_group_id' => (string) Str::uuid()])->save();
            }

            /** @var ProductionHierarchy $hierarchy */
            $hierarchy = ProductionHierarchy::query()
                ->where('root_production_order_id', $lockedRoot->id)
                ->where('planning_version', (int) ($lockedRoot->hierarchy_planning_version ?: 1))
                ->lockForUpdate()
                ->first();

            if (! $hierarchy) {
                $hierarchy = new ProductionHierarchy([
                    'root_production_order_id' => $lockedRoot->id,
                    'planning_version' => (int) ($lockedRoot->hierarchy_planning_version ?: 1),
                    'created_by' => $userId,
                ]);
            }

            ProductionHierarchy::allowServiceMutation(function () use ($hierarchy, $lockedRoot, $explosion, $userId): void {
                $hierarchy->fill([
                    'business_id' => $lockedRoot->getAttribute('business_id'),
                    'status' => ProductionHierarchyStatus::Exploded,
                    'readiness_classification' => ProductionHierarchyReadinessClassification::Ready,
                    'max_depth' => $explosion['max_depth'],
                    'node_count' => $explosion['node_count'],
                    'manufactured_component_count' => $explosion['manufactured_count'],
                    'planned_quantity_base' => $explosion['root']['planned_output_quantity_base'],
                    'planned_uom_code' => $explosion['root']['unit_of_measure_code'],
                    'updated_by' => $userId,
                    'metadata' => [
                        'phase' => '2a.2',
                        'source' => 'multi_level_bom_explosion',
                    ],
                ])->save();
            });

            $nodesByPath = $this->upsertNodes($hierarchy, $lockedRoot, $explosion);
            $this->deleteStalePlanningRows($hierarchy, array_keys($nodesByPath));

            return [
                'hierarchy' => $hierarchy->fresh(),
                'nodes_by_path' => $nodesByPath,
            ];
        });
    }

    public function assertCanPlan(ProductionOrder $order): void
    {
        if (! in_array($order->status, [
            ProductionOrderStatus::SIMULATED,
            ProductionOrderStatus::PLANNED,
            ProductionOrderStatus::FIRM_PLANNED,
        ], true)) {
            throw new RuntimeException('Only simulated, planned, or firm planned production orders can be multi-level planned.');
        }

        if ($order->itemLedgerEntries()->exists() || $order->capacityLedgerEntries()->exists()) {
            throw new RuntimeException('Production orders with ledger activity cannot be replanned.');
        }

        $rootOrderId = $order->root_production_order_id ?: $order->id;
        $memberOrderIds = ProductionOrder::query()
            ->where('id', $rootOrderId)
            ->orWhere('root_production_order_id', $rootOrderId)
            ->pluck('id');

        $hasMemberLedgerActivity = ProductionOrder::query()
            ->whereIn('id', $memberOrderIds)
            ->where(function ($query): void {
                $query->whereHas('itemLedgerEntries')
                    ->orWhereHas('capacityLedgerEntries');
            })
            ->exists();

        if ($hasMemberLedgerActivity) {
            throw new RuntimeException('Production hierarchy cannot be replanned after root or child ledger activity exists.');
        }

        $hasIrreversibleReservationActivity = ProductionMaterialReservation::query()
            ->whereIn('production_order_id', $memberOrderIds)
            ->where(function ($query): void {
                $query->whereRaw('coalesce(quantity_base, 0) <> coalesce(remaining_quantity_base, 0)')
                    ->orWhereNotNull('child_output_item_ledger_entry_id')
                    ->orWhereNotNull('consumed_at');
            })
            ->exists();

        if ($hasIrreversibleReservationActivity) {
            throw new RuntimeException('Production hierarchy cannot be destructively replanned after reservation fulfilment or consumption.');
        }
    }

    /**
     * @param  array{
     *     root: array<string, mixed>,
     *     nodes: array<int, array<string, mixed>>
     * }  $explosion
     * @return array<string, ProductionHierarchyNode>
     */
    private function upsertNodes(ProductionHierarchy $hierarchy, ProductionOrder $rootOrder, array $explosion): array
    {
        $nodesByPath = [];

        $rootNode = $this->upsertNode(
            hierarchy: $hierarchy,
            rootOrder: $rootOrder,
            payload: [
                ...$explosion['root'],
                'parent_path' => null,
                'node_type' => ProductionHierarchyNodeType::RootOutput,
                'line_basis' => null,
                'snapshot' => ['root_order_id' => $rootOrder->id],
            ],
        );
        $nodesByPath[$rootNode->node_path] = $rootNode;

        foreach ($explosion['nodes'] as $nodePayload) {
            $parentNode = $nodesByPath[$nodePayload['parent_path']] ?? null;
            $node = $this->upsertNode(
                hierarchy: $hierarchy,
                rootOrder: $rootOrder,
                payload: [
                    ...$nodePayload,
                    'parent_node_id' => $parentNode?->id,
                    'snapshot' => [
                        'source_line_number' => $nodePayload['source_line_number'],
                        'source_bom_version_line_id' => $nodePayload['source_bom_version_line_id'] ?? null,
                        'line_type' => $nodePayload['line_type'],
                        'quantity_per' => $nodePayload['quantity_per'],
                        'scrap_percent' => $nodePayload['scrap_percent'],
                    ],
                ],
            );

            $nodesByPath[$node->node_path] = $node;
        }

        return $nodesByPath;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertNode(ProductionHierarchy $hierarchy, ProductionOrder $rootOrder, array $payload): ProductionHierarchyNode
    {
        return ProductionHierarchyNode::allowServiceMutation(function () use ($hierarchy, $rootOrder, $payload): ProductionHierarchyNode {
            $node = ProductionHierarchyNode::query()->firstOrNew([
                'idempotency_key' => $this->nodeIdempotencyKey($hierarchy, (string) $payload['path']),
            ]);

            $node->fill([
                'business_id' => $hierarchy->business_id,
                'production_hierarchy_id' => $hierarchy->id,
                'root_production_order_id' => $rootOrder->id,
                'production_order_id' => $payload['path'] === '1' ? $rootOrder->id : ($payload['production_order_id'] ?? null),
                'parent_node_id' => $payload['parent_node_id'] ?? null,
                'node_path' => $payload['path'],
                'level' => $payload['level'],
                'node_type' => $payload['node_type'],
                'status' => ProductionHierarchyNodeStatus::Planned,
                'item_id' => $payload['item_id'],
                'item_no' => $payload['item_no'],
                'description' => $payload['description'],
                'unit_of_measure_code' => $payload['unit_of_measure_code'],
                'required_quantity_base' => DecimalMath::quantity($payload['required_quantity_base']),
                'remaining_required_quantity_base' => DecimalMath::quantity($payload['required_quantity_base']),
                'planned_output_quantity_base' => DecimalMath::quantity($payload['planned_output_quantity_base'] ?? '0'),
                'reserved_quantity_base' => '0',
                'supplied_quantity_base' => '0',
                'source_bom_id' => $payload['source_bom_id'] ?? null,
                'source_bom_version_id' => $payload['source_bom_version_id'] ?? null,
                'source_bom_line_id' => $payload['source_bom_line_id'] ?? null,
                'line_basis' => $payload['line_basis'] ?? null,
                'snapshot' => $payload['snapshot'] ?? [],
                'metadata' => [
                    'phase' => '2a.2',
                    'is_manufactured_requirement' => $payload['is_manufactured_requirement'] ?? false,
                    'child_bom_id' => $payload['child_bom_id'] ?? null,
                    'child_bom_version_id' => $payload['child_bom_version_id'] ?? null,
                    'parent_order_path' => $payload['parent_order_path'] ?? null,
                    'line' => [
                        'flushing_method' => $payload['flushing_method'] ?? 'MANUAL',
                        'routing_link_code' => $payload['routing_link_code'] ?? null,
                        'location_code' => $payload['location_code'] ?? null,
                        'bin_code' => $payload['bin_code'] ?? null,
                        'quantity_per' => $payload['quantity_per'] ?? null,
                        'scrap_percent' => $payload['scrap_percent'] ?? null,
                        'source_bom_code' => $payload['source_bom_code'] ?? null,
                        'source_line_number' => $payload['source_line_number'] ?? null,
                    ],
                ],
            ])->save();

            return $node->fresh();
        });
    }

    /**
     * @param  array<int, string>  $activePaths
     */
    private function deleteStalePlanningRows(ProductionHierarchy $hierarchy, array $activePaths): void
    {
        $staleNodes = $hierarchy->nodes()
            ->whereNotIn('node_path', $activePaths)
            ->get();

        if ($staleNodes->isEmpty()) {
            return;
        }

        $staleNodeIds = $staleNodes->pluck('id')->all();

        ProductionMaterialReservation::allowServiceMutation(
            fn () => ProductionMaterialReservation::query()
                ->whereIn('production_hierarchy_node_id', $staleNodeIds)
                ->delete(),
        );
        ProductionOrderSupplyLink::allowServiceMutation(
            fn () => ProductionOrderSupplyLink::query()
                ->whereIn('production_hierarchy_node_id', $staleNodeIds)
                ->delete(),
        );
        ProductionHierarchyNode::allowServiceMutation(
            fn () => ProductionHierarchyNode::query()
                ->whereIn('id', $staleNodeIds)
                ->delete(),
        );
    }

    private function nodeIdempotencyKey(ProductionHierarchy $hierarchy, string $path): string
    {
        return 'phase2a2:hierarchy-node:'.Str::of($hierarchy->root_production_order_id.'|'.$hierarchy->planning_version.'|'.$path)->slug(':');
    }
}
