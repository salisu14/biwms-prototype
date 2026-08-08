<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionHierarchyStatus;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionOrder;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class MultiLevelProductionPlanningService
{
    public function __construct(
        private readonly MultiLevelBomExplosionService $explosionService,
        private readonly ProductionHierarchyService $hierarchyService,
        private readonly ChildProductionOrderGenerationService $childOrderGenerationService,
        private readonly ProductionMaterialReservationService $reservationService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @return array{
     *     hierarchy: ProductionHierarchy,
     *     root_order: ProductionOrder,
     *     child_order_count: int,
     *     manufactured_component_count: int,
     *     node_count: int
     * }
     */
    public function plan(ProductionOrder $rootOrder, ?int $userId = null, int $maxDepth = 25): array
    {
        return DB::transaction(function () use ($rootOrder, $userId, $maxDepth): array {
            $explosion = $this->explosionService->explode($rootOrder->fresh(), $maxDepth);
            $hierarchyResult = $this->hierarchyService->persist($rootOrder->fresh(), $explosion, $userId);
            $childResult = $this->childOrderGenerationService->generate(
                rootOrder: $rootOrder->fresh(),
                hierarchy: $hierarchyResult['hierarchy'],
                nodesByPath: $hierarchyResult['nodes_by_path'],
                userId: $userId,
            );

            $this->reservationService->reserveGeneratedChildSupply(
                rootOrder: $rootOrder->fresh(),
                hierarchy: $hierarchyResult['hierarchy'],
                nodesByPath: $hierarchyResult['nodes_by_path'],
                componentsByNodeId: $childResult['components_by_node_id'],
                ordersByPath: $childResult['orders_by_path'],
                userId: $userId,
            );

            ProductionHierarchy::allowServiceMutation(function () use ($hierarchyResult, $userId): void {
                $hierarchyResult['hierarchy']->forceFill([
                    'status' => ProductionHierarchyStatus::ChildrenGenerated,
                    'updated_by' => $userId,
                ])->save();
            });

            $hierarchy = $hierarchyResult['hierarchy']->fresh(['nodes', 'supplyLinks', 'materialReservations']);

            $this->auditTrailService->recordGeneric(
                eventType: 'manufacturing_planning',
                action: 'multi_level_planned',
                auditable: $hierarchy,
                documentType: 'PRODUCTION_HIERARCHY',
                documentNo: $rootOrder->document_number,
                source: $rootOrder,
                userId: $userId,
                description: "Multi-level production planned for {$rootOrder->document_number}",
                metadata: [
                    'phase' => '2a.2',
                    'node_count' => $hierarchy->node_count,
                    'manufactured_component_count' => $hierarchy->manufactured_component_count,
                    'child_order_count' => count($childResult['orders_by_path']) - 1,
                ],
            );

            return [
                'hierarchy' => $hierarchy,
                'root_order' => $rootOrder->fresh(),
                'child_order_count' => count($childResult['orders_by_path']) - 1,
                'manufactured_component_count' => (int) $hierarchy->manufactured_component_count,
                'node_count' => (int) $hierarchy->node_count,
            ];
        });
    }

    /**
     * @return array{
     *     root: array<string, mixed>,
     *     nodes: array<int, array<string, mixed>>,
     *     manufactured_count: int,
     *     node_count: int,
     *     max_depth: int
     * }
     */
    public function preview(ProductionOrder $rootOrder, int $maxDepth = 25): array
    {
        return $this->explosionService->explode($rootOrder, $maxDepth);
    }
}
