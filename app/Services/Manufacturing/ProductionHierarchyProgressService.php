<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionHierarchyNodeStatus;
use App\Enums\ProductionHierarchyStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionSupplyLinkStatus;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionOrder;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;

class ProductionHierarchyProgressService
{
    public function syncForOrder(ProductionOrder $order): void
    {
        $rootOrderId = $order->root_production_order_id ?: $order->id;

        ProductionHierarchy::query()
            ->where('root_production_order_id', $rootOrderId)
            ->get()
            ->each(fn (ProductionHierarchy $hierarchy): mixed => $this->sync($hierarchy));
    }

    public function sync(ProductionHierarchy $hierarchy): ProductionHierarchy
    {
        return DB::transaction(function () use ($hierarchy): ProductionHierarchy {
            /** @var ProductionHierarchy $locked */
            $locked = ProductionHierarchy::query()
                ->with(['rootProductionOrder', 'nodes.productionOrder', 'supplyLinks'])
                ->lockForUpdate()
                ->findOrFail($hierarchy->id);

            $this->syncNodeStatuses($locked);
            $status = $this->deriveHierarchyStatus($locked->fresh(['rootProductionOrder', 'nodes.productionOrder', 'supplyLinks']));

            ProductionHierarchy::allowServiceMutation(function () use ($locked, $status): void {
                $locked->forceFill(['status' => $status])->save();
            });

            return $locked->fresh();
        });
    }

    private function syncNodeStatuses(ProductionHierarchy $hierarchy): void
    {
        foreach ($hierarchy->nodes as $node) {
            $status = $this->deriveNodeStatus($node);

            ProductionHierarchyNode::allowServiceMutation(function () use ($node, $status): void {
                $node->forceFill(['status' => $status])->save();
            });
        }
    }

    private function deriveNodeStatus(ProductionHierarchyNode $node): ProductionHierarchyNodeStatus
    {
        $order = $node->productionOrder;
        if ($order?->status === ProductionOrderStatus::CANCELLED) {
            return ProductionHierarchyNodeStatus::Cancelled;
        }

        if ($order?->status === ProductionOrderStatus::RELEASED) {
            return ProductionHierarchyNodeStatus::Released;
        }

        if ($order?->status === ProductionOrderStatus::FINISHED) {
            return ProductionHierarchyNodeStatus::Current;
        }

        return ProductionHierarchyNodeStatus::Planned;
    }

    private function deriveHierarchyStatus(ProductionHierarchy $hierarchy): ProductionHierarchyStatus
    {
        if ($hierarchy->rootProductionOrder?->status === ProductionOrderStatus::CANCELLED) {
            return ProductionHierarchyStatus::Cancelled;
        }

        $links = $hierarchy->supplyLinks;

        if ($links->contains(fn ($link): bool => $link->status === ProductionSupplyLinkStatus::Exception)) {
            return ProductionHierarchyStatus::Exception;
        }

        $hasLedgerActivity = $hierarchy->nodes
            ->pluck('productionOrder')
            ->filter()
            ->contains(fn (ProductionOrder $order): bool => $order->itemLedgerEntries()->exists() || $order->capacityLedgerEntries()->exists());

        if (
            $hierarchy->rootProductionOrder?->status === ProductionOrderStatus::FINISHED
            && $links->every(fn ($link): bool => DecimalMath::compare($link->consumed_quantity_base, $link->required_quantity_base) >= 0)
        ) {
            return ProductionHierarchyStatus::Completed;
        }

        if ($links->contains(fn ($link): bool => DecimalMath::isPositive($link->supplied_quantity_base))) {
            return ProductionHierarchyStatus::PartiallyCompleted;
        }

        if ($hasLedgerActivity) {
            return ProductionHierarchyStatus::InProgress;
        }

        if ($hierarchy->nodes->contains(fn (ProductionHierarchyNode $node): bool => $node->productionOrder?->status === ProductionOrderStatus::RELEASED)) {
            return ProductionHierarchyStatus::Released;
        }

        return ProductionHierarchyStatus::ChildrenGenerated;
    }
}
