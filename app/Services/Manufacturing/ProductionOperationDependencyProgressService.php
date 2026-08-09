<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionIntermediateHandoffStatus;
use App\Enums\ProductionOperationDependencyReadiness;
use App\Enums\ProductionOperationDependencyStatus;
use App\Models\Manufacturing\ProductionOperationDependency;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;

class ProductionOperationDependencyProgressService
{
    public function __construct(
        private readonly ProductionOperationDependencyReadinessService $readinessService,
    ) {}

    public function syncForProductionOrder(ProductionOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            ProductionOperationDependency::query()
                ->where(function ($query) use ($order): void {
                    $query->where('upstream_production_order_id', $order->id)
                        ->orWhere('downstream_production_order_id', $order->id);
                })
                ->lockForUpdate()
                ->get()
                ->each(fn (ProductionOperationDependency $dependency): ProductionOperationDependency => $this->syncDependency($dependency));
        });
    }

    public function syncForSupplyLink(ProductionOrderSupplyLink $link): void
    {
        ProductionOperationDependency::query()
            ->where('production_order_supply_link_id', $link->id)
            ->get()
            ->each(fn (ProductionOperationDependency $dependency): ProductionOperationDependency => $this->syncDependency($dependency));
    }

    public function syncDependency(ProductionOperationDependency $dependency): ProductionOperationDependency
    {
        return DB::transaction(function () use ($dependency): ProductionOperationDependency {
            /** @var ProductionOperationDependency $locked */
            $locked = ProductionOperationDependency::query()
                ->with('supplyLink.materialReservations')
                ->lockForUpdate()
                ->findOrFail($dependency->id);

            $finding = $this->readinessService->findingForDependency($locked);
            $fulfilledQuantityBase = DecimalMath::quantity($locked->supplyLink?->supplied_quantity_base ?? $locked->fulfilled_quantity_base);
            $requiredQuantityBase = DecimalMath::quantity($locked->required_quantity_base);
            $status = match ($finding['classification']) {
                ProductionOperationDependencyReadiness::Ready->value => DecimalMath::compare($fulfilledQuantityBase, $requiredQuantityBase) >= 0
                    ? ProductionOperationDependencyStatus::Fulfilled
                    : ProductionOperationDependencyStatus::Ready,
                ProductionOperationDependencyReadiness::PartiallyReady->value => ProductionOperationDependencyStatus::PartiallyReady,
                ProductionOperationDependencyReadiness::InvalidDependency->value,
                ProductionOperationDependencyReadiness::UpstreamCancelled->value => ProductionOperationDependencyStatus::Invalid,
                default => ProductionOperationDependencyStatus::Blocked,
            };

            $locked->forceFill([
                'fulfilled_quantity_base' => $fulfilledQuantityBase,
                'status' => $status,
                'last_evaluated_at' => now(),
                'metadata' => [
                    ...(array) $locked->metadata,
                    'last_readiness' => $finding,
                ],
            ])->save();

            $this->syncHandoff($locked);

            return $locked->fresh();
        });
    }

    private function syncHandoff(ProductionOperationDependency $dependency): void
    {
        $handoff = $dependency->handoffs()->lockForUpdate()->first();
        if (! $handoff) {
            return;
        }

        $link = $dependency->supplyLink;
        $quantityAvailableBase = DecimalMath::quantity($link?->supplied_quantity_base ?? $dependency->fulfilled_quantity_base);
        $quantityTransferredBase = DecimalMath::quantity($link?->consumed_quantity_base ?? 0);
        $quantityRequiredBase = DecimalMath::quantity($handoff->quantity_required_base);
        $qualityBlocked = (array) ($dependency->metadata['last_readiness'] ?? []) !== []
            && ($dependency->metadata['last_readiness']['classification'] ?? null) === ProductionOperationDependencyReadiness::WaitingForQualityRelease->value;

        $status = match (true) {
            $qualityBlocked => ProductionIntermediateHandoffStatus::QualityBlocked,
            DecimalMath::compare($quantityTransferredBase, $quantityRequiredBase) >= 0 => ProductionIntermediateHandoffStatus::Consumed,
            DecimalMath::isPositive($quantityTransferredBase) => ProductionIntermediateHandoffStatus::PartiallyConsumed,
            DecimalMath::compare($quantityAvailableBase, $quantityRequiredBase) >= 0 => ProductionIntermediateHandoffStatus::Available,
            DecimalMath::isPositive($quantityAvailableBase) => ProductionIntermediateHandoffStatus::PartiallyAvailable,
            default => ProductionIntermediateHandoffStatus::WaitingOutput,
        };

        $handoff->forceFill([
            'child_output_item_ledger_entry_id' => $link?->materialReservations()->value('child_output_item_ledger_entry_id') ?? $handoff->child_output_item_ledger_entry_id,
            'quantity_available_base' => $quantityAvailableBase,
            'quantity_transferred_base' => $quantityTransferredBase,
            'status' => $status,
            'quality_status' => $qualityBlocked ? 'blocked' : 'released_or_not_required',
            'last_synced_at' => now(),
        ])->save();
    }
}
