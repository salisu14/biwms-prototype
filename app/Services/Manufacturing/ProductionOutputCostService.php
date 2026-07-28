<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionOutputAllocationStatus;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionOutputCostAllocation;
use App\Services\Inventory\CostingPeriodService;
use App\Services\Inventory\ValueEntryService;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;

class ProductionOutputCostService
{
    public function __construct(
        private readonly ProductionCostSummaryService $summaryService,
        private readonly CostingPeriodService $costingPeriodService,
    ) {}

    public function allocateToOutput(ProductionOrder $order, ItemLedgerEntry $outputEntry, bool $finalOutput = false): ProductionOutputCostAllocation
    {
        return DB::transaction(function () use ($order, $outputEntry, $finalOutput): ProductionOutputCostAllocation {
            /** @var ProductionOrder $lockedOrder */
            $lockedOrder = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            /** @var ItemLedgerEntry $lockedOutput */
            $lockedOutput = ItemLedgerEntry::query()->lockForUpdate()->findOrFail($outputEntry->id);

            $this->costingPeriodService->assertAdjustmentAllowed($lockedOutput->posting_date);

            $idempotencyKey = hash('sha256', implode('|', [
                'production-output-allocation',
                $lockedOrder->id,
                $lockedOutput->id,
                DecimalMath::quantity($lockedOutput->quantity),
            ]));
            $sourceIdentityKey = hash('sha256', implode('|', [
                'production-output-source',
                $lockedOrder->id,
                $lockedOutput->id,
            ]));

            $summary = $this->summaryService->summarize($lockedOrder);
            $eligibleCost = max(0.0, (float) $summary['total_accumulated_cost']);
            $alreadyAllocated = max(0.0, (float) $summary['allocated_output_cost']);
            $existing = ProductionOutputCostAllocation::query()
                ->where(function ($query) use ($idempotencyKey, $sourceIdentityKey): void {
                    $query->where('idempotency_key', $idempotencyKey)
                        ->orWhere('source_identity_key', $sourceIdentityKey);
                })
                ->first();

            if ($existing) {
                $existingStatus = $this->allocationStatusEnum($existing->allocation_status);
                if ($existingStatus === ProductionOutputAllocationStatus::Final) {
                    return $existing;
                }

                $existingAllocatedTotal = (float) $existing->allocated_total_cost;
                if (! $finalOutput && $existingStatus === ProductionOutputAllocationStatus::Provisional && $existingAllocatedTotal > 0.0001) {
                    return $existing;
                }

                $alreadyAllocated = max(0.0, $alreadyAllocated - $existingAllocatedTotal);
            }

            $remainingEligibleCost = max(0.0, $eligibleCost - $alreadyAllocated);
            $totalOutputQuantity = max((float) $lockedOrder->quantity_base, (float) $lockedOrder->quantity, (float) $lockedOutput->quantity);
            $outputQuantity = max(0.0, (float) $lockedOutput->quantity);
            $allocatedTotal = $finalOutput
                ? $remainingEligibleCost
                : min($remainingEligibleCost, round($eligibleCost * ($outputQuantity / $totalOutputQuantity), 4));

            $materialShare = $this->share($allocatedTotal, (float) $summary['actual_material_cost'], $eligibleCost);
            $capacityShare = $this->share($allocatedTotal, (float) $summary['actual_capacity_cost'], $eligibleCost);
            $overheadShare = round($allocatedTotal - $materialShare - $capacityShare, 4);

            if ($allocatedTotal > 0.0001) {
                $lockedOutput->forceFill([
                    'cost_amount_actual' => DecimalMath::amount($allocatedTotal),
                ])->save();
            }

            $valueEntry = app(ValueEntryService::class)->ensureForItemLedgerEntry($lockedOutput->fresh());
            if ($valueEntry && ! $valueEntry->gl_posted && $allocatedTotal > 0.0001) {
                $unitCost = $outputQuantity > 0.0 ? $allocatedTotal / $outputQuantity : 0.0;
                $valueEntry->forceFill([
                    'cost_component' => ManufacturingCostComponent::Output->value,
                    'cost_amount_actual' => DecimalMath::amount($allocatedTotal),
                    'cost_amount_actual_acy' => DecimalMath::amount($allocatedTotal),
                    'unit_cost' => DecimalMath::unitCost($unitCost),
                    'unit_cost_acy' => DecimalMath::unitCost($unitCost),
                    'single_level_material_cost' => DecimalMath::amount($materialShare),
                    'single_level_capacity_cost' => DecimalMath::amount($capacityShare),
                    'single_level_overhead_cost' => DecimalMath::amount($overheadShare),
                    'accounting_metadata' => array_merge($valueEntry->accounting_metadata ?? [], [
                        'phase_1d_output_allocation' => true,
                        'eligible_cost_before_allocation' => $eligibleCost,
                        'already_allocated_before_allocation' => $alreadyAllocated,
                    ]),
                ])->save();
            }

            $nextStatus = $this->allocationStatus($allocatedTotal, $finalOutput);

            if ($existing && ! $this->allocationStatusEnum($existing->allocation_status)->canTransitionTo($nextStatus)) {
                throw new \RuntimeException(sprintf(
                    'Invalid production output allocation transition from %s to %s.',
                    $this->allocationStatusEnum($existing->allocation_status)->value,
                    $nextStatus->value,
                ));
            }

            $allocationValues = [
                'production_order_id' => $lockedOrder->id,
                'output_item_ledger_entry_id' => $lockedOutput->id,
                'output_value_entry_id' => $valueEntry?->id,
                'output_quantity' => DecimalMath::quantity($outputQuantity),
                'eligible_cost_before_allocation' => DecimalMath::amount($eligibleCost),
                'allocated_material_cost' => DecimalMath::amount($materialShare),
                'allocated_capacity_cost' => DecimalMath::amount($capacityShare),
                'allocated_overhead_cost' => DecimalMath::amount($overheadShare),
                'allocated_total_cost' => DecimalMath::amount($allocatedTotal),
                'allocation_status' => $nextStatus->value,
                'is_final_allocation' => $finalOutput,
                'finalized_at' => $finalOutput ? now() : null,
                'source_identity_key' => $sourceIdentityKey,
                'metadata' => [
                    'allocation_method' => 'eligible_cost_times_output_quantity_over_total_order_output_quantity',
                    'remaining_eligible_cost_after_allocation' => round($remainingEligibleCost - $allocatedTotal, 4),
                ],
            ];

            if ($existing) {
                $existing->forceFill($allocationValues)->save();

                return $existing->fresh();
            }

            return ProductionOutputCostAllocation::query()->create([
                ...$allocationValues,
                'idempotency_key' => $idempotencyKey,
                'source_identity_key' => $sourceIdentityKey,
            ]);
        });
    }

    private function share(float $allocatedTotal, float $componentAmount, float $eligibleCost): float
    {
        if ($eligibleCost <= 0.0001) {
            return 0.0;
        }

        return round($allocatedTotal * ($componentAmount / $eligibleCost), 4);
    }

    private function allocationStatus(float $allocatedTotal, bool $finalOutput): ProductionOutputAllocationStatus
    {
        if ($finalOutput) {
            return ProductionOutputAllocationStatus::Final;
        }

        return $allocatedTotal > 0.0001
            ? ProductionOutputAllocationStatus::Provisional
            : ProductionOutputAllocationStatus::Pending;
    }

    private function allocationStatusEnum(mixed $status): ProductionOutputAllocationStatus
    {
        if ($status instanceof ProductionOutputAllocationStatus) {
            return $status;
        }

        return ProductionOutputAllocationStatus::tryFrom((string) $status)
            ?? throw new \RuntimeException("Unsupported production output allocation status [{$status}].");
    }
}
