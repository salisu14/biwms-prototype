<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionSupplyLinkStatus;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Support\DecimalMath;
use Illuminate\Support\Collection;
use RuntimeException;

class MultiLevelProductionReadinessService
{
    /**
     * @return array{ready: bool, reasons: array<int, array<string, mixed>>}
     */
    public function releaseReadiness(ProductionOrder $order): array
    {
        return $this->readiness($order, requireFullyConsumed: false);
    }

    /**
     * @return array{ready: bool, reasons: array<int, array<string, mixed>>}
     */
    public function completionReadiness(ProductionOrder $order): array
    {
        return $this->readiness($order, requireFullyConsumed: true);
    }

    public function assertCanFinish(ProductionOrder $order): void
    {
        $readiness = $this->completionReadiness($order);

        if ($readiness['ready']) {
            return;
        }

        $firstReason = $readiness['reasons'][0]['message'] ?? 'Unresolved hierarchy manufactured-component demand.';

        throw new RuntimeException($firstReason);
    }

    /**
     * @return array{ready: bool, reasons: array<int, array<string, mixed>>}
     */
    private function readiness(ProductionOrder $order, bool $requireFullyConsumed): array
    {
        $components = $order->components()
            ->where('is_manufactured_requirement', true)
            ->with(['supplyLinks.childProductionOrder', 'materialReservations.productionOrderSupplyLink'])
            ->get();

        $reasons = $components
            ->flatMap(fn (ProductionOrderComponent $component): Collection => $this->componentReasons($component, $requireFullyConsumed))
            ->values()
            ->all();

        return [
            'ready' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    private function componentReasons(ProductionOrderComponent $component, bool $requireFullyConsumed): Collection
    {
        $reasons = collect();
        $requiredQuantityBase = DecimalMath::quantity($component->required_supply_quantity_base ?? $component->expected_quantity_base);
        $actualConsumedBase = DecimalMath::quantity($component->actual_quantity_consumed ?? '0');
        $link = $component->supplyLinks
            ->where('status', '!=', ProductionSupplyLinkStatus::Cancelled)
            ->first();
        $reservation = $component->materialReservations
            ->first(fn ($reservation): bool => ! in_array((string) ($reservation->status?->value ?? $reservation->status), ['released', 'cancelled', 'expired'], true));

        if (! $link) {
            $reasons->push($this->reason($component, 'child_supply_not_started', 'Manufactured component has no generated child supply link.'));

            return $reasons;
        }

        $childOrder = $link->childProductionOrder;
        if (! $childOrder) {
            $reasons->push($this->reason($component, 'child_order_missing', 'Manufactured component supply link has no child production order.'));
        } elseif ($childOrder->status === ProductionOrderStatus::CANCELLED) {
            $reasons->push($this->reason($component, 'child_order_cancelled', "Child production order {$childOrder->document_number} is cancelled."));
        }

        if (! $reservation) {
            $reasons->push($this->reason($component, 'reservation_shortfall', 'Manufactured component has no active child-output reservation.'));
        }

        if ($requireFullyConsumed) {
            if (DecimalMath::compare($link->supplied_quantity_base, $requiredQuantityBase) < 0) {
                $reasons->push($this->reason($component, 'child_supply_shortfall', 'Child supply is not fully available for manufactured component demand.'));
            }

            if (DecimalMath::compare($actualConsumedBase, $requiredQuantityBase) < 0) {
                $reasons->push($this->reason($component, 'manufactured_component_not_consumed', 'Manufactured component demand has not been fully consumed by the parent order.'));
            }
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    private function reason(ProductionOrderComponent $component, string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'component_id' => $component->id,
            'item_id' => $component->item_id,
            'line_number' => $component->line_number,
        ];
    }
}
