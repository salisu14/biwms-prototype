<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionOutputAllocationStatus;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionOutputCostAllocation;
use App\Models\ValueEntry;
use App\Services\Inventory\CostingPeriodService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
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
                $existingAllocatedTotal = (float) $existing->allocated_total_cost;
                $alreadyAllocated = max(0.0, $alreadyAllocated - $existingAllocatedTotal);

                if ($existingStatus === ProductionOutputAllocationStatus::Final && ! $finalOutput) {
                    return $existing;
                }

                if (! $finalOutput && $existingStatus === ProductionOutputAllocationStatus::Provisional && $existingAllocatedTotal > 0.0001) {
                    return $existing;
                }
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

            $valueEntry = $this->syncOutputValueEntry(
                order: $lockedOrder,
                outputEntry: $lockedOutput,
                allocatedTotal: $allocatedTotal,
                outputQuantity: $outputQuantity,
                materialShare: $materialShare,
                capacityShare: $capacityShare,
                overheadShare: $overheadShare,
                eligibleCost: $eligibleCost,
                alreadyAllocated: $alreadyAllocated,
            );

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

    private function syncOutputValueEntry(
        ProductionOrder $order,
        ItemLedgerEntry $outputEntry,
        float $allocatedTotal,
        float $outputQuantity,
        float $materialShare,
        float $capacityShare,
        float $overheadShare,
        float $eligibleCost,
        float $alreadyAllocated
    ): ?ValueEntry {
        $valueEntry = app(ValueEntryService::class)->ensureForItemLedgerEntry($outputEntry->fresh());

        if (! $valueEntry || $allocatedTotal <= 0.0001) {
            return $valueEntry;
        }

        if (! $valueEntry->gl_posted) {
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

            return $valueEntry->fresh();
        }

        $currentOutputValue = $this->currentOutputValue($outputEntry);
        $delta = round($allocatedTotal - $currentOutputValue, 4);

        if (abs($delta) <= 0.0001) {
            return $valueEntry;
        }

        $adjustment = $this->createOutputCostAdjustmentValueEntry($order, $outputEntry, $valueEntry, $delta, $allocatedTotal);
        app(ValueEntryAccountingOrchestrator::class)->post($adjustment);

        return $valueEntry;
    }

    private function currentOutputValue(ItemLedgerEntry $outputEntry): float
    {
        return (float) ValueEntry::query()
            ->where('item_ledger_entry_no', $outputEntry->entry_number)
            ->where('document_no', $outputEntry->document_number)
            ->where('document_line_no', $outputEntry->document_line_number)
            ->where('expected_cost', false)
            ->where('cost_component', ManufacturingCostComponent::Output->value)
            ->whereNotIn('value_entry_state', ['reversed', 'cleared'])
            ->sum('cost_amount_actual');
    }

    private function createOutputCostAdjustmentValueEntry(
        ProductionOrder $order,
        ItemLedgerEntry $outputEntry,
        ValueEntry $baseValueEntry,
        float $delta,
        float $allocatedTotal
    ): ValueEntry {
        $idempotencyKey = hash('sha256', implode('|', [
            'production-output-cost-settlement-adjustment',
            $order->id,
            $outputEntry->id,
            DecimalMath::amount($allocatedTotal),
        ]));

        $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        return ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            'item_ledger_entry_no' => $outputEntry->entry_number,
            'item_ledger_entry_type' => $baseValueEntry->item_ledger_entry_type ?: 7,
            'item_no' => $baseValueEntry->item_no,
            'location_code' => $baseValueEntry->location_code,
            'posting_date' => $baseValueEntry->posting_date,
            'valuation_date' => $baseValueEntry->valuation_date ?? $baseValueEntry->posting_date,
            'document_type' => 'PROD_OUTPUT_COST_ADJ',
            'document_no' => $order->document_number,
            'document_line_no' => $outputEntry->document_line_number,
            'description' => 'Production output cost settlement adjustment',
            'quantity' => 0,
            'invoiced_quantity' => 0,
            'valued_quantity' => 0,
            'remaining_quantity' => 0,
            'cost_component' => ManufacturingCostComponent::Output->value,
            'value_entry_state' => 'adjustment',
            'cost_amount_actual' => DecimalMath::amount($delta),
            'cost_amount_actual_acy' => DecimalMath::amount($delta),
            'unit_cost' => 0,
            'unit_cost_acy' => 0,
            'single_level_material_cost' => 0,
            'single_level_capacity_cost' => 0,
            'single_level_overhead_cost' => 0,
            'source_module' => 'manufacturing',
            'source_type' => ProductionOrder::class,
            'source_id' => $order->id,
            'source_number' => $order->document_number,
            'source_no' => (string) $order->id,
            'source_line_no' => $outputEntry->document_line_number,
            'production_order_no' => $order->document_number,
            'production_order_line_no' => $baseValueEntry->production_order_line_no,
            'prod_order_line_item_no' => $baseValueEntry->prod_order_line_item_no,
            'expected_cost' => false,
            'cost_adjusted' => true,
            'cost_adjustment_date' => now()->toDateString(),
            'original_entry_no' => $baseValueEntry->id,
            'idempotency_key' => $idempotencyKey,
            'accounting_metadata' => [
                'phase_1d_output_settlement_adjustment' => true,
                'base_value_entry_id' => $baseValueEntry->id,
                'output_item_ledger_entry_id' => $outputEntry->id,
                'allocated_total_cost_after_adjustment' => DecimalMath::amount($allocatedTotal),
                'adjustment_delta' => DecimalMath::amount($delta),
            ],
        ]);
    }
}
