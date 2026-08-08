<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Enums\ProductionSupplyLinkStatus;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Support\DecimalFormatter;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionReservationConsumptionService
{
    public function assertComponentConsumptionAllowed(ProductionOrderComponent $component, string $quantityBase): void
    {
        if (! $component->is_manufactured_requirement) {
            return;
        }

        $reservation = $this->activeReservationForComponent($component);
        if (! $reservation) {
            throw new RuntimeException('Manufactured component has no active child-output reservation.');
        }

        $availableToConsumeBase = $this->availableToConsumeBase($reservation);

        if (DecimalMath::compare($quantityBase, $availableToConsumeBase) > 0) {
            throw new RuntimeException(
                'Cannot consume more hierarchy child supply than is available. '.
                'Requested: '.DecimalFormatter::quantity($quantityBase).
                ', Available: '.DecimalFormatter::quantity($availableToConsumeBase)
            );
        }
    }

    public function syncComponentConsumption(ProductionOrderComponent $component): void
    {
        if (! $component->is_manufactured_requirement) {
            return;
        }

        DB::transaction(function () use ($component): void {
            /** @var ProductionOrderComponent $lockedComponent */
            $lockedComponent = ProductionOrderComponent::query()
                ->lockForUpdate()
                ->findOrFail($component->id);

            $reservation = $this->activeReservationForComponent($lockedComponent, lock: true);
            if (! $reservation) {
                return;
            }

            $reservedQuantityBase = DecimalMath::quantity($reservation->quantity_base);
            $consumedQuantityBase = DecimalMath::quantity(min((float) $lockedComponent->actual_quantity_consumed, (float) $reservedQuantityBase));
            $remainingQuantityBase = DecimalMath::quantity(max(0.0, (float) $reservedQuantityBase - (float) $consumedQuantityBase));
            $availableQuantityBase = $this->availableQuantityBase($reservation);

            if (DecimalMath::compare($consumedQuantityBase, $availableQuantityBase) > 0) {
                throw new RuntimeException('Hierarchy reservation consumption exceeds supplied child output.');
            }

            ProductionMaterialReservation::allowServiceMutation(function () use ($reservation, $consumedQuantityBase, $remainingQuantityBase, $availableQuantityBase): void {
                $reservation->forceFill([
                    'remaining_quantity_base' => $remainingQuantityBase,
                    'status' => $this->reservationStatus($consumedQuantityBase, $reservation->quantity_base),
                    'consumed_at' => DecimalMath::isZero($remainingQuantityBase) ? ($reservation->consumed_at ?? now()) : null,
                    'metadata' => [
                        ...(array) $reservation->metadata,
                        'phase_2a3' => true,
                        'available_quantity_base' => $availableQuantityBase,
                        'consumed_quantity_base' => $consumedQuantityBase,
                        'last_consumption_sync_at' => now()->toISOString(),
                    ],
                ])->save();
            });

            $link = $reservation->productionOrderSupplyLink()->lockForUpdate()->first();
            if ($link) {
                ProductionOrderSupplyLink::allowServiceMutation(function () use ($link, $consumedQuantityBase): void {
                    $link->forceFill([
                        'consumed_quantity_base' => $consumedQuantityBase,
                        'status' => $this->supplyStatusAfterConsumption($link, $consumedQuantityBase),
                    ])->save();
                });
            }
        });
    }

    public function releaseReservation(ProductionMaterialReservation $reservation, ?int $userId = null): void
    {
        ProductionMaterialReservation::allowServiceMutation(function () use ($reservation, $userId): void {
            $reservation->forceFill([
                'status' => ProductionReservationStatus::Released,
                'released_at' => now(),
                'updated_by' => $userId,
            ])->save();
        });
    }

    private function activeReservationForComponent(ProductionOrderComponent $component, bool $lock = false): ?ProductionMaterialReservation
    {
        $query = $component->materialReservations()
            ->where('reservation_type', ProductionReservationType::ChildOutput)
            ->whereNotIn('status', [
                ProductionReservationStatus::Released->value,
                ProductionReservationStatus::Cancelled->value,
                ProductionReservationStatus::Expired->value,
            ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function availableToConsumeBase(ProductionMaterialReservation $reservation): string
    {
        $availableQuantityBase = $this->availableQuantityBase($reservation);
        $consumedQuantityBase = DecimalMath::sub($reservation->quantity_base, $reservation->remaining_quantity_base, DecimalPrecision::QUANTITY_SCALE);
        $availableToConsumeBase = DecimalMath::sub($availableQuantityBase, $consumedQuantityBase, DecimalPrecision::QUANTITY_SCALE);

        return DecimalMath::quantity(max(0.0, (float) $availableToConsumeBase));
    }

    private function availableQuantityBase(ProductionMaterialReservation $reservation): string
    {
        $link = $reservation->productionOrderSupplyLink;
        $suppliedQuantityBase = DecimalMath::quantity($link?->supplied_quantity_base ?? '0');
        $reservedQuantityBase = DecimalMath::quantity($reservation->quantity_base);

        return DecimalMath::compare($suppliedQuantityBase, $reservedQuantityBase) > 0
            ? $reservedQuantityBase
            : $suppliedQuantityBase;
    }

    private function reservationStatus(string $consumedQuantityBase, mixed $reservedQuantityBase): ProductionReservationStatus
    {
        if (DecimalMath::compare($consumedQuantityBase, $reservedQuantityBase) >= 0) {
            return ProductionReservationStatus::Consumed;
        }

        if (DecimalMath::isPositive($consumedQuantityBase)) {
            return ProductionReservationStatus::PartiallyConsumed;
        }

        return ProductionReservationStatus::Active;
    }

    private function supplyStatusAfterConsumption(ProductionOrderSupplyLink $link, string $consumedQuantityBase): ProductionSupplyLinkStatus
    {
        if (DecimalMath::compare($consumedQuantityBase, $link->required_quantity_base) >= 0) {
            return ProductionSupplyLinkStatus::Supplied;
        }

        if (DecimalMath::isPositive($consumedQuantityBase)) {
            return ProductionSupplyLinkStatus::PartiallySupplied;
        }

        if (DecimalMath::compare($link->supplied_quantity_base, $link->required_quantity_base) >= 0) {
            return ProductionSupplyLinkStatus::Available;
        }

        if (DecimalMath::isPositive($link->supplied_quantity_base)) {
            return ProductionSupplyLinkStatus::PartiallyProduced;
        }

        return ProductionSupplyLinkStatus::ChildOrderCreated;
    }
}
