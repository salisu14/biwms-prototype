<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionVarianceType;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionExpectedCostSnapshot;
use App\Models\ProductionVarianceCalculation;
use App\Support\DecimalMath;
use App\Support\DecimalTolerance;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductionVarianceCalculationService
{
    public function __construct(
        private readonly ProductionValueEntryOwnership $ownership,
    ) {}

    /**
     * @return Collection<int, ProductionVarianceCalculation>
     */
    public function calculate(ProductionOrder $order, ?ProductionExpectedCostSnapshot $snapshot = null, mixed $postingDate = null, ?int $userId = null): Collection
    {
        $postingDate = Carbon::parse($postingDate ?? now())->startOfDay();
        $snapshot ??= $order->expectedCostSnapshots()->latest('id')->first();

        return DB::transaction(function () use ($order, $snapshot, $postingDate, $userId): Collection {
            /** @var ProductionOrder $lockedOrder */
            $lockedOrder = ProductionOrder::query()
                ->with(['item'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $entries = $this->manufacturingValueEntries($lockedOrder);
            $actualOutputQuantity = abs((float) $entries
                ->whereIn('cost_component', [ManufacturingCostComponent::Output->value, 'output'])
                ->where('expected_cost', false)
                ->sum('quantity'));

            if ($actualOutputQuantity <= 0.0) {
                $actualOutputQuantity = abs((float) $lockedOrder->quantity_base);
            }

            $calculations = collect();
            $calculations = $calculations->merge($this->materialVariances($lockedOrder, $snapshot, $entries, $actualOutputQuantity, $postingDate, $userId));
            $calculations = $calculations->merge($this->capacityVariances($lockedOrder, $snapshot, $entries, $actualOutputQuantity, $postingDate, $userId));
            $calculations = $calculations->merge($this->standardCostVariance($lockedOrder, $entries, $actualOutputQuantity, $postingDate, $userId));

            return $calculations->values();
        });
    }

    /**
     * @return Collection<int, ProductionVarianceCalculation>
     */
    private function materialVariances(
        ProductionOrder $order,
        ?ProductionExpectedCostSnapshot $snapshot,
        Collection $entries,
        float $actualOutputQuantity,
        Carbon $postingDate,
        ?int $userId
    ): Collection {
        $componentDetails = collect($snapshot?->component_details ?? []);
        $actualMaterialEntries = $entries
            ->whereIn('cost_component', [ManufacturingCostComponent::DirectMaterial->value, 'material'])
            ->where('expected_cost', false);

        return $componentDetails
            ->map(function (array $detail) use ($order, $snapshot, $actualMaterialEntries, $actualOutputQuantity, $postingDate, $userId): array {
                $itemNo = (string) ($detail['item_no'] ?? '');
                $actualEntries = $actualMaterialEntries->where('item_no', $itemNo);
                $actualQuantity = abs((float) $actualEntries->sum('quantity'));
                $actualAmount = abs((float) $actualEntries->sum('cost_amount_actual'));
                $expectedQuantity = (float) ($detail['expected_quantity_base'] ?? 0);
                $plannedQuantity = max((float) ($snapshot?->production_quantity_base ?? $order->quantity_base), 0.00000001);
                $expectedAllowedQuantity = $expectedQuantity * ($actualOutputQuantity / $plannedQuantity);
                $expectedRate = (float) ($detail['unit_cost'] ?? 0);
                $actualRate = $actualQuantity > 0.0 ? $actualAmount / $actualQuantity : 0.0;

                return [
                    $this->record(
                        order: $order,
                        snapshot: $snapshot,
                        type: ProductionVarianceType::MaterialPrice,
                        expectedQuantity: $actualQuantity,
                        actualQuantity: $actualQuantity,
                        expectedRate: $expectedRate,
                        actualRate: $actualRate,
                        expectedAmount: $actualQuantity * $expectedRate,
                        actualAmount: $actualAmount,
                        varianceAmount: $actualQuantity * ($actualRate - $expectedRate),
                        postingDate: $postingDate,
                        userId: $userId,
                        metadata: $detail,
                    ),
                    $this->record(
                        order: $order,
                        snapshot: $snapshot,
                        type: ProductionVarianceType::MaterialQuantity,
                        expectedQuantity: $expectedAllowedQuantity,
                        actualQuantity: $actualQuantity,
                        expectedRate: $expectedRate,
                        actualRate: $expectedRate,
                        expectedAmount: $expectedAllowedQuantity * $expectedRate,
                        actualAmount: $actualQuantity * $expectedRate,
                        varianceAmount: ($actualQuantity - $expectedAllowedQuantity) * $expectedRate,
                        postingDate: $postingDate,
                        userId: $userId,
                        metadata: $detail,
                    ),
                ];
            })
            ->flatten()
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, ProductionVarianceCalculation>
     */
    private function capacityVariances(
        ProductionOrder $order,
        ?ProductionExpectedCostSnapshot $snapshot,
        Collection $entries,
        float $actualOutputQuantity,
        Carbon $postingDate,
        ?int $userId
    ): Collection {
        $routingDetails = collect($snapshot?->routing_details ?? []);
        $actualCapacity = $entries
            ->whereIn('cost_component', [ManufacturingCostComponent::DirectCapacity->value, 'capacity'])
            ->where('expected_cost', false);
        $actualOverhead = $entries
            ->whereIn('cost_component', [ManufacturingCostComponent::CapacityOverhead->value, 'overhead'])
            ->where('expected_cost', false);

        return $routingDetails
            ->map(function (array $detail) use ($order, $snapshot, $actualCapacity, $actualOverhead, $actualOutputQuantity, $postingDate, $userId): array {
                $lineNumber = (string) ($detail['line_number'] ?? '');
                $actualCapacityEntries = $actualCapacity->where('source_line_no', $lineNumber);
                $actualOverheadEntries = $actualOverhead->where('source_line_no', $lineNumber);
                $actualTime = abs((float) $actualCapacityEntries->sum('quantity'));
                $actualCapacityAmount = abs((float) $actualCapacityEntries->sum('cost_amount_actual'));
                $actualOverheadAmount = abs((float) $actualOverheadEntries->sum('cost_amount_actual'));
                $expectedTime = (float) ($detail['costed_time_minutes'] ?? 0);
                $plannedQuantity = max((float) ($snapshot?->production_quantity_base ?? $order->quantity_base), 0.00000001);
                $expectedAllowedTime = $expectedTime * ($actualOutputQuantity / $plannedQuantity);
                $expectedRate = (float) ($detail['direct_rate'] ?? 0);
                $actualRate = $actualTime > 0.0 ? $actualCapacityAmount / $actualTime : 0.0;
                $expectedOverheadAmount = (float) ($detail['expected_overhead_amount'] ?? 0) * ($actualOutputQuantity / $plannedQuantity);

                return [
                    $this->record(
                        order: $order,
                        snapshot: $snapshot,
                        type: ProductionVarianceType::CapacityRate,
                        expectedQuantity: $actualTime,
                        actualQuantity: $actualTime,
                        expectedRate: $expectedRate,
                        actualRate: $actualRate,
                        expectedAmount: $actualTime * $expectedRate,
                        actualAmount: $actualCapacityAmount,
                        varianceAmount: $actualTime * ($actualRate - $expectedRate),
                        postingDate: $postingDate,
                        userId: $userId,
                        metadata: $detail,
                    ),
                    $this->record(
                        order: $order,
                        snapshot: $snapshot,
                        type: ProductionVarianceType::CapacityEfficiency,
                        expectedQuantity: $expectedAllowedTime,
                        actualQuantity: $actualTime,
                        expectedRate: $expectedRate,
                        actualRate: $expectedRate,
                        expectedAmount: $expectedAllowedTime * $expectedRate,
                        actualAmount: $actualTime * $expectedRate,
                        varianceAmount: ($actualTime - $expectedAllowedTime) * $expectedRate,
                        postingDate: $postingDate,
                        userId: $userId,
                        metadata: $detail,
                    ),
                    $this->record(
                        order: $order,
                        snapshot: $snapshot,
                        type: ProductionVarianceType::CapacityOverhead,
                        expectedQuantity: $expectedAllowedTime,
                        actualQuantity: $actualTime,
                        expectedRate: $expectedAllowedTime > 0 ? $expectedOverheadAmount / $expectedAllowedTime : 0,
                        actualRate: $actualTime > 0 ? $actualOverheadAmount / $actualTime : 0,
                        expectedAmount: $expectedOverheadAmount,
                        actualAmount: $actualOverheadAmount,
                        varianceAmount: $actualOverheadAmount - $expectedOverheadAmount,
                        postingDate: $postingDate,
                        userId: $userId,
                        metadata: $detail,
                    ),
                ];
            })
            ->flatten()
            ->filter()
            ->values();
    }

    private function standardCostVariance(ProductionOrder $order, Collection $entries, float $actualOutputQuantity, Carbon $postingDate, ?int $userId): Collection
    {
        $method = strtoupper((string) ($order->costing_method?->value ?? $order->costing_method ?? $order->item?->costing_method?->value ?? $order->item?->costing_method ?? ''));
        if ($method !== 'STANDARD') {
            return collect();
        }

        $actualCost = abs((float) $entries
            ->whereIn('cost_component', [
                ManufacturingCostComponent::DirectMaterial->value,
                ManufacturingCostComponent::DirectCapacity->value,
                ManufacturingCostComponent::CapacityOverhead->value,
                'material',
                'capacity',
                'overhead',
            ])
            ->where('expected_cost', false)
            ->sum('cost_amount_actual'));
        $standardCost = $actualOutputQuantity * (float) ($order->unit_cost ?: $order->item?->standard_cost ?: 0);

        return collect([
            $this->record(
                order: $order,
                snapshot: $order->expectedCostSnapshots()->latest('id')->first(),
                type: ProductionVarianceType::StandardCost,
                expectedQuantity: $actualOutputQuantity,
                actualQuantity: $actualOutputQuantity,
                expectedRate: (float) ($order->unit_cost ?: $order->item?->standard_cost ?: 0),
                actualRate: $actualOutputQuantity > 0.0 ? $actualCost / $actualOutputQuantity : 0.0,
                expectedAmount: $standardCost,
                actualAmount: $actualCost,
                varianceAmount: $actualCost - $standardCost,
                postingDate: $postingDate,
                userId: $userId,
                metadata: ['standard_cost_source' => 'production_order_or_item_standard_cost'],
            ),
        ])->filter();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function record(
        ProductionOrder $order,
        ?ProductionExpectedCostSnapshot $snapshot,
        ProductionVarianceType $type,
        float $expectedQuantity,
        float $actualQuantity,
        float $expectedRate,
        float $actualRate,
        float $expectedAmount,
        float $actualAmount,
        float $varianceAmount,
        Carbon $postingDate,
        ?int $userId,
        array $metadata
    ): ?ProductionVarianceCalculation {
        if (abs($varianceAmount) <= DecimalTolerance::AMOUNT) {
            return null;
        }

        $identity = hash('sha256', implode('|', [
            'production-variance-calculation',
            $order->id,
            $snapshot?->id,
            $type->value,
            DecimalMath::quantity($expectedQuantity),
            DecimalMath::quantity($actualQuantity),
            DecimalMath::amount($varianceAmount),
        ]));

        return ProductionVarianceCalculation::query()->firstOrCreate(
            ['calculation_identity' => $identity],
            [
                'production_order_id' => $order->id,
                'production_expected_cost_snapshot_id' => $snapshot?->id,
                'variance_type' => $type->value,
                'cost_component' => $type->costComponent()->value,
                'expected_quantity' => DecimalMath::quantity($expectedQuantity),
                'actual_quantity' => DecimalMath::quantity($actualQuantity),
                'expected_rate' => DecimalMath::unitCost($expectedRate),
                'actual_rate' => DecimalMath::unitCost($actualRate),
                'expected_amount' => DecimalMath::amount($expectedAmount),
                'actual_amount' => DecimalMath::amount($actualAmount),
                'variance_amount' => DecimalMath::amount($varianceAmount),
                'variance_reason' => $type->value,
                'posting_date' => $postingDate->toDateString(),
                'original_source_date' => $postingDate->toDateString(),
                'metadata' => $metadata,
                'calculated_by' => $userId,
                'calculated_at' => now(),
            ],
        );
    }

    private function manufacturingValueEntries(ProductionOrder $order): Collection
    {
        return $this->ownership
            ->belongsToOrderQuery($order)
            ->get();
    }
}
