<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;

class ProductionSupplyFulfilmentService
{
    public function syncChildOutputSupply(ProductionOrder $childOrder): void
    {
        DB::transaction(function () use ($childOrder): void {
            /** @var ProductionOrder $child */
            $child = ProductionOrder::query()
                ->with(['supplyLinksAsChild.materialReservations'])
                ->lockForUpdate()
                ->findOrFail($childOrder->id);

            $links = $child->supplyLinksAsChild()
                ->where('supply_type', ProductionSupplyType::GeneratedChildOrder)
                ->where('status', '!=', ProductionSupplyLinkStatus::Cancelled->value)
                ->lockForUpdate()
                ->get();

            foreach ($links as $link) {
                $this->syncLinkFromChildOutput($child, $link);
            }
        });
    }

    public function syncLinkFromChildOutput(ProductionOrder $childOrder, ProductionOrderSupplyLink $link): ProductionOrderSupplyLink
    {
        $totalOutputQuantityBase = $this->childOutputQuantityBase($childOrder, (int) $link->item_id);
        $requiredQuantityBase = DecimalMath::quantity($link->required_quantity_base);
        $suppliedQuantityBase = DecimalMath::compare($totalOutputQuantityBase, $requiredQuantityBase) > 0
            ? $requiredQuantityBase
            : $totalOutputQuantityBase;
        $status = $this->supplyStatus($suppliedQuantityBase, $requiredQuantityBase, $childOrder);
        $latestOutputEntryId = $this->latestChildOutputEntryId($childOrder, (int) $link->item_id);

        ProductionOrderSupplyLink::allowServiceMutation(function () use ($link, $totalOutputQuantityBase, $suppliedQuantityBase, $status): void {
            $link->forceFill([
                'produced_quantity_base' => $totalOutputQuantityBase,
                'supplied_quantity_base' => $suppliedQuantityBase,
                'status' => $status,
                'metadata' => [
                    ...(array) $link->metadata,
                    'phase_2a3' => true,
                    'excess_output_quantity_base' => DecimalMath::quantity(max(0.0, (float) $totalOutputQuantityBase - (float) $link->required_quantity_base)),
                    'last_supply_sync_at' => now()->toISOString(),
                ],
            ])->save();
        });

        foreach ($link->materialReservations()->lockForUpdate()->get() as $reservation) {
            $this->syncReservationAvailability($reservation, $link, $latestOutputEntryId);
        }

        return $link->fresh();
    }

    private function syncReservationAvailability(
        ProductionMaterialReservation $reservation,
        ProductionOrderSupplyLink $link,
        ?int $latestOutputEntryId,
    ): void {
        $reservedQuantityBase = DecimalMath::quantity($reservation->quantity_base);
        $consumedQuantityBase = DecimalMath::sub($reservedQuantityBase, $reservation->remaining_quantity_base, DecimalPrecision::QUANTITY_SCALE);
        $availableQuantityBase = DecimalMath::compare($link->supplied_quantity_base, $reservedQuantityBase) > 0
            ? $reservedQuantityBase
            : DecimalMath::quantity($link->supplied_quantity_base);

        $status = $this->reservationStatus($consumedQuantityBase, $availableQuantityBase, $reservedQuantityBase, $reservation);

        ProductionMaterialReservation::allowServiceMutation(function () use ($reservation, $status, $availableQuantityBase, $consumedQuantityBase, $latestOutputEntryId): void {
            $reservation->forceFill([
                'child_output_item_ledger_entry_id' => $latestOutputEntryId,
                'status' => $status,
                'metadata' => [
                    ...(array) $reservation->metadata,
                    'phase_2a3' => true,
                    'available_quantity_base' => $availableQuantityBase,
                    'consumed_quantity_base' => $consumedQuantityBase,
                    'last_availability_sync_at' => now()->toISOString(),
                ],
            ])->save();
        });
    }

    private function childOutputQuantityBase(ProductionOrder $childOrder, int $itemId): string
    {
        return DecimalMath::quantity($childOrder->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->where('item_id', $itemId)
            ->sum('quantity'));
    }

    private function latestChildOutputEntryId(ProductionOrder $childOrder, int $itemId): ?int
    {
        return $childOrder->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->where('item_id', $itemId)
            ->latest('id')
            ->value('id');
    }

    private function supplyStatus(string $suppliedQuantityBase, string $requiredQuantityBase, ProductionOrder $childOrder): ProductionSupplyLinkStatus
    {
        if (DecimalMath::compare($suppliedQuantityBase, $requiredQuantityBase) >= 0) {
            return ProductionSupplyLinkStatus::Available;
        }

        if (DecimalMath::isPositive($suppliedQuantityBase)) {
            return ProductionSupplyLinkStatus::PartiallyProduced;
        }

        if ($childOrder->status->isCompleted()) {
            return ProductionSupplyLinkStatus::Exception;
        }

        return ProductionSupplyLinkStatus::ChildOrderCreated;
    }

    private function reservationStatus(
        string $consumedQuantityBase,
        string $availableQuantityBase,
        string $reservedQuantityBase,
        ProductionMaterialReservation $reservation,
    ): ProductionReservationStatus {
        if (in_array($reservation->status, [
            ProductionReservationStatus::Released,
            ProductionReservationStatus::Cancelled,
            ProductionReservationStatus::Expired,
        ], true)) {
            return $reservation->status;
        }

        if (DecimalMath::compare($consumedQuantityBase, $reservedQuantityBase) >= 0) {
            return ProductionReservationStatus::Consumed;
        }

        if (DecimalMath::isPositive($consumedQuantityBase)) {
            return ProductionReservationStatus::PartiallyConsumed;
        }

        return ProductionReservationStatus::Active;
    }
}
