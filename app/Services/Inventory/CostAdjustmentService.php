<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionCostSettlementClassification;
use App\Enums\ProductionCostSettlementStatus;
use App\Models\CostAdjustmentBatch;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ValueEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CostAdjustmentService
{
    public function __construct(
        private readonly CostingPeriodService $costingPeriodService,
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
        private readonly ValueEntryEconomicValueService $economicValueService,
    ) {}

    /**
     * @return array{batch: CostAdjustmentBatch, adjustments: list<array<string, mixed>|ValueEntry>}
     */
    public function adjustInboundCost(ItemLedgerEntry $inboundEntry, float $newTotalCost, string $reason, bool $dryRun = true, ?int $userId = null): array
    {
        return DB::transaction(function () use ($inboundEntry, $newTotalCost, $reason, $dryRun, $userId): array {
            /** @var ItemLedgerEntry $inbound */
            $inbound = ItemLedgerEntry::query()
                ->with(['item', 'location'])
                ->lockForUpdate()
                ->findOrFail($inboundEntry->id);

            $this->costingPeriodService->assertAdjustmentAllowed($inbound->posting_date);
            $adjustmentPostingDate = $this->costingPeriodService->adjustmentPostingDate($inbound->posting_date);
            $currentCost = DecimalMath::amount($inbound->cost_amount_actual ?: $inbound->cost_amount_expected);
            $newTotalCostAmount = DecimalMath::amount($newTotalCost);
            $delta = DecimalMath::sub($newTotalCostAmount, $currentCost, DecimalPrecision::AMOUNT_SCALE);

            $batch = CostAdjustmentBatch::query()->firstOrCreate(
                [
                    'batch_number' => $this->batchNumber($inbound, $newTotalCost, $reason, $dryRun),
                ],
                [
                    'source_type' => ItemLedgerEntry::class,
                    'source_id' => $inbound->id,
                    'reason' => $reason,
                    'dry_run' => $dryRun,
                    'run_at' => now(),
                    'run_by' => $userId,
                    'metadata' => [
                        'old_total_cost' => $currentCost,
                        'new_total_cost' => $newTotalCostAmount,
                        'delta' => $delta,
                        'posting_date' => $adjustmentPostingDate->toDateString(),
                    ],
                ],
            );

            if (abs((float) $delta) <= 0.0001) {
                return ['batch' => $batch, 'adjustments' => []];
            }

            $applications = ItemApplicationEntry::query()
                ->where('inbound_item_ledger_entry_id', $inbound->id)
                ->where('is_reversed', false)
                ->with('outboundItemLedgerEntry')
                ->lockForUpdate()
                ->get();

            $adjustments = [];
            $inboundQuantity = DecimalMath::abs($inbound->quantity, DecimalPrecision::QUANTITY_SCALE);
            $correctedInboundUnitCost = DecimalMath::isZero($inboundQuantity)
                ? DecimalMath::unitCost('0')
                : DecimalMath::div($newTotalCostAmount, $inboundQuantity, DecimalPrecision::UNIT_COST_SCALE);
            $consumedQuantity = DecimalMath::quantity($applications->sum(fn (ItemApplicationEntry $application): float => abs((float) $application->applied_quantity)));
            $remainingQuantity = DecimalMath::quantity($inbound->remaining_quantity);
            $consumedDelta = DecimalMath::amount('0');

            foreach ($applications as $application) {
                $targetEconomicCost = $this->economicValueService->targetCostForApplication($application, $correctedInboundUnitCost);
                $currentEconomicCost = $this->economicValueService->currentEconomicCostForApplication($application);
                $adjustmentAmount = DecimalMath::sub($targetEconomicCost, $currentEconomicCost, DecimalPrecision::AMOUNT_SCALE);
                $consumedDelta = DecimalMath::add($consumedDelta, $adjustmentAmount, DecimalPrecision::AMOUNT_SCALE);

                if (abs((float) $adjustmentAmount) <= 0.0001) {
                    if ($dryRun) {
                        $adjustments[] = [
                            'outbound_item_ledger_entry_id' => $application->outbound_item_ledger_entry_id,
                            'adjustment_amount' => DecimalMath::amount('0'),
                            'applied_quantity' => DecimalMath::quantity($application->applied_quantity),
                            'current_economic_cost' => $currentEconomicCost,
                            'target_economic_cost' => $targetEconomicCost,
                            'outstanding_adjustment_required' => DecimalMath::amount('0'),
                        ];
                    }

                    continue;
                }

                if ($dryRun) {
                    $adjustments[] = [
                        'outbound_item_ledger_entry_id' => $application->outbound_item_ledger_entry_id,
                        'adjustment_amount' => $adjustmentAmount,
                        'applied_quantity' => DecimalMath::quantity($application->applied_quantity),
                        'current_economic_cost' => $currentEconomicCost,
                        'target_economic_cost' => $targetEconomicCost,
                        'outstanding_adjustment_required' => $adjustmentAmount,
                    ];

                    continue;
                }

                $adjustments[] = $this->createAdjustmentValueEntry(
                    batch: $batch,
                    application: $application,
                    adjustmentAmount: $adjustmentAmount,
                    adjustmentPostingDate: $adjustmentPostingDate,
                    reason: $reason,
                    userId: $userId,
                );

                $this->markProductionOrderForLateCostAdjustment($application, $batch, $adjustmentAmount);
            }

            $remainingDelta = DecimalMath::sub($delta, $consumedDelta, DecimalPrecision::AMOUNT_SCALE);

            $batch->forceFill([
                'metadata' => [
                    ...(array) $batch->metadata,
                    'old_total_cost' => $currentCost,
                    'new_total_cost' => $newTotalCostAmount,
                    'delta' => $delta,
                    'consumed_quantity' => $consumedQuantity,
                    'remaining_quantity' => $remainingQuantity,
                    'consumed_delta' => $consumedDelta,
                    'remaining_inventory_delta' => $remainingDelta,
                    'corrected_inbound_unit_cost' => $correctedInboundUnitCost,
                    'posting_date' => $adjustmentPostingDate->toDateString(),
                ],
            ])->save();

            if ($dryRun) {
                return [
                    'batch' => $batch->fresh(),
                    'adjustments' => $adjustments,
                    'summary' => [
                        'total_delta' => $delta,
                        'consumed_quantity' => $consumedQuantity,
                        'remaining_quantity' => $remainingQuantity,
                        'consumed_delta' => $consumedDelta,
                        'remaining_inventory_delta' => $remainingDelta,
                        'corrected_inbound_unit_cost' => $correctedInboundUnitCost,
                        'posting_date' => $adjustmentPostingDate->toDateString(),
                    ],
                ];
            }

            if (abs((float) $remainingDelta) > 0.0001) {
                $adjustments[] = $this->createRemainingInventoryRevaluationValueEntry(
                    batch: $batch,
                    inbound: $inbound,
                    adjustmentAmount: $remainingDelta,
                    adjustmentPostingDate: $adjustmentPostingDate,
                    reason: $reason,
                    userId: $userId,
                );
            }

            if (! $dryRun) {
                $inbound->forceFill([
                    'cost_amount_actual' => $newTotalCostAmount,
                ])->save();
            }

            return [
                'batch' => $batch->fresh(),
                'adjustments' => $adjustments,
                'summary' => [
                    'total_delta' => $delta,
                    'consumed_quantity' => $consumedQuantity,
                    'remaining_quantity' => $remainingQuantity,
                    'consumed_delta' => $consumedDelta,
                    'remaining_inventory_delta' => $remainingDelta,
                    'corrected_inbound_unit_cost' => $correctedInboundUnitCost,
                    'posting_date' => $adjustmentPostingDate->toDateString(),
                ],
            ];
        });
    }

    private function markProductionOrderForLateCostAdjustment(ItemApplicationEntry $application, CostAdjustmentBatch $batch, string $adjustmentAmount): void
    {
        $outbound = $application->outboundItemLedgerEntry;
        if (! $outbound || strtolower((string) ($outbound->entry_type?->value ?? $outbound->entry_type)) !== ItemLedgerEntryType::CONSUMPTION->value) {
            return;
        }

        $productionOrder = $outbound->source instanceof ProductionOrder
            ? $outbound->source
            : ProductionOrder::query()->find($outbound->source_id);

        if (! $productionOrder) {
            return;
        }

        $this->createProductionCostAdjustmentValueEntry($productionOrder, $outbound, $batch, $adjustmentAmount);

        $currentStatus = $productionOrder->cost_settlement_status instanceof ProductionCostSettlementStatus
            ? $productionOrder->cost_settlement_status
            : ProductionCostSettlementStatus::tryFrom((string) $productionOrder->cost_settlement_status);

        if ($currentStatus === ProductionCostSettlementStatus::Settled) {
            $productionOrder->forceFill([
                'cost_settlement_status' => ProductionCostSettlementStatus::AdjustmentRequired->value,
                'cost_settlement_classification' => ProductionCostSettlementClassification::LateCostAdjustmentRequired->value,
            ])->save();
        }
    }

    private function createProductionCostAdjustmentValueEntry(ProductionOrder $order, ItemLedgerEntry $consumptionEntry, CostAdjustmentBatch $batch, string $adjustmentAmount): ?ValueEntry
    {
        $idempotencyKey = hash('sha256', implode('|', [
            'production-late-material-cost-adjustment',
            $batch->id,
            $order->id,
            $consumptionEntry->id,
            DecimalMath::amount($adjustmentAmount),
        ]));

        $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        /** @var ValueEntry $valueEntry */
        $valueEntry = ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            'item_ledger_entry_no' => $consumptionEntry->entry_number,
            'item_ledger_entry_type' => 6,
            'item_no' => (string) ($consumptionEntry->item?->item_code ?? $consumptionEntry->item_id),
            'location_code' => (string) ($consumptionEntry->location?->code ?? $consumptionEntry->location_id ?? 'MAIN'),
            'posting_date' => $batch->metadata['posting_date'] ?? now()->toDateString(),
            'valuation_date' => $consumptionEntry->posting_date,
            'document_type' => 'PRODUCTION_COST_ADJUSTMENT',
            'document_no' => $batch->batch_number,
            'document_line_no' => $consumptionEntry->document_line_number,
            'description' => 'Late material cost propagation to production',
            'quantity' => 0,
            'invoiced_quantity' => 0,
            'valued_quantity' => 0,
            'remaining_quantity' => 0,
            'cost_component' => ManufacturingCostComponent::CostAdjustment->value,
            'value_entry_state' => 'adjustment',
            'cost_amount_actual' => DecimalMath::amount($adjustmentAmount),
            'cost_amount_actual_acy' => DecimalMath::amount($adjustmentAmount),
            'unit_cost' => 0,
            'unit_cost_acy' => 0,
            'source_type' => CostAdjustmentBatch::class,
            'source_module' => 'manufacturing',
            'source_id' => $batch->id,
            'source_number' => $batch->batch_number,
            'source_no' => (string) $order->id,
            'source_line_no' => $consumptionEntry->document_line_number,
            'production_order_no' => $order->document_number,
            'production_order_component_line_no' => $consumptionEntry->document_line_number,
            'prod_order_line_item_no' => $order->item?->item_code,
            'expected_cost' => false,
            'cost_adjusted' => true,
            'cost_adjustment_date' => now()->toDateString(),
            'original_entry_no' => $consumptionEntry->id,
            'idempotency_key' => $idempotencyKey,
            'accounting_metadata' => [
                'phase_1d_late_material_cost_propagation' => true,
                'cost_adjustment_batch_id' => $batch->id,
                'consumption_item_ledger_entry_id' => $consumptionEntry->id,
            ],
        ]);

        $this->accountingOrchestrator->post($valueEntry);

        return $valueEntry->fresh();
    }

    private function createAdjustmentValueEntry(
        CostAdjustmentBatch $batch,
        ItemApplicationEntry $application,
        string $adjustmentAmount,
        Carbon $adjustmentPostingDate,
        string $reason,
        ?int $userId
    ): ValueEntry {
        $outbound = $application->outboundItemLedgerEntry;
        $outbound?->loadMissing(['item', 'location']);
        $idempotencyKey = hash('sha256', implode('|', [
            'cost-adjustment',
            $batch->id,
            $application->id,
            DecimalMath::amount($adjustmentAmount),
        ]));

        $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        /** @var ValueEntry $valueEntry */
        $valueEntry = ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            'item_ledger_entry_no' => $outbound?->entry_number,
            'item_ledger_entry_type' => $this->mapItemLedgerType($outbound?->entry_type?->value ?? $outbound?->entry_type),
            'item_no' => (string) ($outbound?->item?->item_code ?? $outbound?->item_id),
            'location_code' => (string) ($outbound?->location?->code ?? $outbound?->location_id ?? 'MAIN'),
            'posting_date' => $adjustmentPostingDate->toDateString(),
            'valuation_date' => $adjustmentPostingDate->toDateString(),
            'document_type' => 'COST_ADJUSTMENT',
            'document_no' => $batch->batch_number,
            'document_line_no' => $application->id,
            'description' => $reason,
            'quantity' => 0,
            'invoiced_quantity' => 0,
            'valued_quantity' => 0,
            'remaining_quantity' => 0,
            'cost_component' => 'cost_adjustment',
            'value_entry_state' => 'adjustment',
            'cost_amount_actual' => DecimalMath::amount($adjustmentAmount),
            'cost_amount_actual_acy' => DecimalMath::amount($adjustmentAmount),
            'unit_cost' => 0,
            'unit_cost_acy' => 0,
            'source_type' => CostAdjustmentBatch::class,
            'source_module' => 'inventory',
            'source_id' => $batch->id,
            'source_number' => $batch->batch_number,
            'source_no' => (string) $batch->id,
            'source_line_no' => $application->id,
            'expected_cost' => false,
            'cost_adjusted' => true,
            'cost_adjustment_date' => now()->toDateString(),
            'original_entry_no' => $application->inbound_item_ledger_entry_id,
            'idempotency_key' => $idempotencyKey,
            'accounting_metadata' => [
                'phase_1c_cost_adjustment' => true,
                'item_application_entry_id' => $application->id,
                'adjustment_batch_id' => $batch->id,
            ],
            'user_id' => $userId ? (string) $userId : null,
        ]);

        $this->accountingOrchestrator->post($valueEntry);

        return $valueEntry->fresh();
    }

    private function createRemainingInventoryRevaluationValueEntry(
        CostAdjustmentBatch $batch,
        ItemLedgerEntry $inbound,
        string $adjustmentAmount,
        Carbon $adjustmentPostingDate,
        string $reason,
        ?int $userId
    ): ValueEntry {
        $inbound->loadMissing(['item', 'location']);
        $idempotencyKey = hash('sha256', implode('|', [
            'inventory-layer-revaluation',
            $batch->id,
            $inbound->id,
            DecimalMath::amount($adjustmentAmount),
        ]));

        $existing = ValueEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        /** @var ValueEntry $valueEntry */
        $valueEntry = ValueEntry::query()->create([
            'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
            'item_ledger_entry_no' => $inbound->entry_number,
            'item_ledger_entry_type' => 3,
            'item_no' => (string) ($inbound->item?->item_code ?? $inbound->item_id),
            'location_code' => (string) ($inbound->location?->code ?? $inbound->location_id ?? 'MAIN'),
            'posting_date' => $adjustmentPostingDate->toDateString(),
            'valuation_date' => $adjustmentPostingDate->toDateString(),
            'document_type' => 'INVENTORY_REVALUATION',
            'document_no' => $batch->batch_number,
            'document_line_no' => $inbound->document_line_number,
            'description' => $reason,
            'quantity' => 0,
            'invoiced_quantity' => 0,
            'valued_quantity' => 0,
            'remaining_quantity' => 0,
            'cost_component' => ManufacturingCostComponent::CostAdjustment->value,
            'value_entry_state' => 'adjustment',
            'cost_amount_actual' => DecimalMath::amount($adjustmentAmount),
            'cost_amount_actual_acy' => DecimalMath::amount($adjustmentAmount),
            'cost_amount_expected' => 0,
            'cost_amount_expected_acy' => 0,
            'unit_cost' => 0,
            'unit_cost_acy' => 0,
            'source_type' => CostAdjustmentBatch::class,
            'source_module' => 'inventory',
            'source_id' => $batch->id,
            'source_number' => $batch->batch_number,
            'source_no' => (string) $inbound->id,
            'source_line_no' => $inbound->document_line_number,
            'expected_cost' => false,
            'cost_adjusted' => true,
            'cost_adjustment_date' => now()->toDateString(),
            'original_entry_no' => $inbound->id,
            'idempotency_key' => $idempotencyKey,
            'accounting_metadata' => [
                'inventory_layer_revaluation' => true,
                'cost_adjustment_batch_id' => $batch->id,
                'inbound_item_ledger_entry_id' => $inbound->id,
                'remaining_quantity_base' => DecimalMath::quantity($inbound->remaining_quantity),
            ],
            'user_id' => $userId ? (string) $userId : null,
        ]);

        $this->accountingOrchestrator->post($valueEntry);

        return $valueEntry->fresh();
    }

    private function batchNumber(ItemLedgerEntry $inbound, float $newTotalCost, string $reason, bool $dryRun): string
    {
        return 'COSTADJ-'.substr(hash('sha256', implode('|', [
            $dryRun ? 'dry-run' : 'post',
            $inbound->id,
            DecimalMath::amount($newTotalCost),
            $reason,
        ])), 0, 24);
    }

    private function mapItemLedgerType(mixed $entryType): int
    {
        return match (strtolower((string) $entryType)) {
            'purchase' => 1,
            'sale' => 2,
            'positive_adj', 'positive adjustment', 'positive adjmt.' => 3,
            'negative_adj', 'negative adjustment', 'negative adjmt.' => 4,
            'transfer' => 5,
            'consumption' => 6,
            'output' => 7,
            default => 0,
        };
    }
}
