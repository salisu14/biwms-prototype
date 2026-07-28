<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\JournalLineStatus;
use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionCostSettlementClassification;
use App\Enums\ProductionCostSettlementStatus;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionJournalLine;
use App\Models\ValueEntry;
use App\Services\Inventory\CostingPeriodService;
use App\Support\DecimalMath;
use App\Support\DecimalTolerance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use UnitEnum;

class ProductionOrderCostSettlementService
{
    public function __construct(
        private readonly ProductionCostSummaryService $summaryService,
        private readonly ProductionOutputCostService $outputCostService,
        private readonly ProductionVarianceValueEntryService $varianceService,
        private readonly CostingPeriodService $costingPeriodService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function settle(ProductionOrder $order, int $userId, mixed $postingDate = null): array
    {
        return DB::transaction(function () use ($order, $userId, $postingDate): array {
            /** @var ProductionOrder $lockedOrder */
            $lockedOrder = ProductionOrder::query()
                ->with(['itemLedgerEntries', 'capacityLedgerEntries'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->cost_settled_at) {
                return [
                    'settled' => false,
                    'idempotent' => true,
                    'status' => ProductionCostSettlementStatus::Settled->value,
                    'summary' => $this->summaryService->summarize($lockedOrder),
                ];
            }

            $postingDate = $postingDate ?? now();
            $this->costingPeriodService->assertAdjustmentAllowed($postingDate);

            $readiness = $this->evaluateReadiness($lockedOrder);
            if ($readiness['status'] !== 'ready') {
                $this->transitionSettlementState(
                    order: $lockedOrder,
                    status: $readiness['settlement_status'],
                    classification: $readiness['classification'],
                );

                return [
                    'settled' => false,
                    'idempotent' => false,
                    'status' => $readiness['settlement_status']->value,
                    'classification' => $readiness['classification']->value,
                    'reasons' => $readiness['reasons'],
                    'summary' => $this->summaryService->summarize($lockedOrder->fresh()),
                ];
            }

            $outputEntries = $lockedOrder->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->orderBy('id')
                ->get();

            if ($outputEntries->isEmpty()) {
                throw new RuntimeException("Production order {$lockedOrder->document_number} cannot be settled without output entries.");
            }

            foreach ($outputEntries as $index => $outputEntry) {
                $this->outputCostService->allocateToOutput(
                    order: $lockedOrder,
                    outputEntry: $outputEntry,
                    finalOutput: $index === $outputEntries->count() - 1,
                );
            }

            $summary = $this->summaryService->summarize($lockedOrder);
            $variance = round((float) $summary['total_accumulated_cost'] - (float) $summary['allocated_output_cost'], 4);
            $varianceClassification = $this->classifySettlementDifference($lockedOrder, $summary, $variance);
            $varianceEntry = null;

            if ($varianceClassification === ProductionCostSettlementClassification::TrueProductionVariance && abs($variance) > 0.0001) {
                $varianceEntry = $this->varianceService->recordVariance(
                    order: $lockedOrder,
                    varianceAmount: $variance,
                    component: $this->isStandardCosting($lockedOrder)
                        ? ManufacturingCostComponent::StandardCostVariance
                        : ManufacturingCostComponent::RoundingVariance,
                    postingDate: $postingDate,
                    userId: $userId,
                    reason: 'Production order final cost settlement',
                );
            }

            $lockedOrder->forceFill([
                'cost_settled_at' => now(),
                'cost_settled_by' => $userId,
                'cost_settlement_key' => hash('sha256', 'production-cost-settlement|'.$lockedOrder->id.'|'.$lockedOrder->document_number),
            ])->save();
            $this->transitionSettlementState(
                order: $lockedOrder,
                status: ProductionCostSettlementStatus::Settled,
                classification: $varianceClassification,
            );

            return [
                'settled' => true,
                'idempotent' => false,
                'status' => ProductionCostSettlementStatus::Settled->value,
                'classification' => $varianceClassification->value,
                'variance' => DecimalMath::amount($variance),
                'variance_value_entry_id' => $varianceEntry?->id,
                'summary' => $this->summaryService->summarize($lockedOrder->fresh()),
            ];
        });
    }

    /**
     * @return array{status: string, settlement_status: ProductionCostSettlementStatus, classification: ProductionCostSettlementClassification, reasons: array<int, string>}
     */
    private function evaluateReadiness(ProductionOrder $order): array
    {
        $reasons = [];

        if (! in_array($order->status, [ProductionOrderStatus::RELEASED, ProductionOrderStatus::FINISHED], true)) {
            $reasons[] = ProductionCostSettlementClassification::ProductionOrderNotOperationallyFinished->value;
        }

        if (! $order->getPostingSetup()) {
            $reasons[] = ProductionCostSettlementClassification::PostingSetupMissing->value;
        }

        if (! DecimalMath::isLessThanOrEqualToTolerance($order->remaining_quantity, DecimalTolerance::QUANTITY)) {
            $reasons[] = ProductionCostSettlementClassification::RequiredOutputNotPosted->value;
        }

        $remainingConsumption = (float) $order->components()
            ->selectRaw('COALESCE(SUM(COALESCE(remaining_quantity, 0)), 0) as remaining_quantity')
            ->value('remaining_quantity');

        if ($remainingConsumption > DecimalTolerance::QUANTITY) {
            $reasons[] = ProductionCostSettlementClassification::RequiredConsumptionNotPosted->value;
        }

        $incompleteRoutingLines = $order->routingLines()
            ->where(function (Builder $query): void {
                $query->whereRaw('(COALESCE(setup_time, 0) - COALESCE(actual_setup_time, 0)) > ?', [DecimalTolerance::QUANTITY])
                    ->orWhereRaw('(COALESCE(run_time, 0) - COALESCE(actual_run_time, 0)) > ?', [DecimalTolerance::QUANTITY]);
            })
            ->exists();

        if ($incompleteRoutingLines) {
            $reasons[] = ProductionCostSettlementClassification::RequiredCapacityNotPosted->value;
        }

        $unresolvedJournalLines = ProductionJournalLine::query()
            ->where('production_order_id', $order->id)
            ->whereNotIn('line_status', [JournalLineStatus::POSTED->value])
            ->exists();

        if ($unresolvedJournalLines) {
            $reasons[] = ProductionCostSettlementClassification::UnresolvedProductionJournalLines->value;
        }

        $pendingExpectedCost = $this->manufacturingValueEntries($order)
            ->where('expected_cost', true)
            ->whereRaw('ABS(COALESCE(cost_amount_expected, 0)) > 0.0001')
            ->exists();

        if ($pendingExpectedCost) {
            $reasons[] = ProductionCostSettlementClassification::PendingExpectedCost->value;
        }

        if ($reasons !== []) {
            return [
                'status' => 'not_ready',
                'settlement_status' => ProductionCostSettlementStatus::NotReady,
                'classification' => ProductionCostSettlementClassification::from($reasons[0]),
                'reasons' => $reasons,
            ];
        }

        return [
            'status' => 'ready',
            'settlement_status' => ProductionCostSettlementStatus::Pending,
            'classification' => ProductionCostSettlementClassification::Ready,
            'reasons' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function classifySettlementDifference(ProductionOrder $order, array $summary, float $difference): ProductionCostSettlementClassification
    {
        if (abs($difference) <= DecimalTolerance::AMOUNT) {
            return ProductionCostSettlementClassification::RoundingResidual;
        }

        if ((float) $summary['cost_not_posted_to_gl'] > DecimalTolerance::AMOUNT) {
            return ProductionCostSettlementClassification::PendingActualMaterialCost;
        }

        if ($this->manufacturingValueEntries($order)
            ->where('expected_cost', true)
            ->whereRaw('ABS(COALESCE(cost_amount_expected, 0)) > 0.0001')
            ->exists()) {
            return ProductionCostSettlementClassification::PendingExpectedCost;
        }

        if ((float) $summary['unallocated_cost'] > DecimalTolerance::AMOUNT) {
            return ProductionCostSettlementClassification::UnallocatedCost;
        }

        return ProductionCostSettlementClassification::TrueProductionVariance;
    }

    private function manufacturingValueEntries(ProductionOrder $order): Builder
    {
        return ValueEntry::query()
            ->where(function (Builder $query) use ($order): void {
                $query->where('production_order_no', $order->document_number)
                    ->orWhere(function (Builder $query) use ($order): void {
                        $query->where('source_module', 'manufacturing')
                            ->where('source_id', $order->id);
                    });
            });
    }

    private function isStandardCosting(ProductionOrder $order): bool
    {
        $costingMethod = $order->costing_method instanceof UnitEnum
            ? $order->costing_method->value
            : (string) $order->costing_method;

        return strtoupper($costingMethod) === 'STANDARD';
    }

    private function transitionSettlementState(
        ProductionOrder $order,
        ProductionCostSettlementStatus $status,
        ProductionCostSettlementClassification $classification
    ): void {
        $current = $this->settlementStatusEnum($order->cost_settlement_status);

        if (! $current->canTransitionTo($status)) {
            throw new RuntimeException(sprintf(
                'Invalid production cost settlement transition from %s to %s.',
                $current->value,
                $status->value,
            ));
        }

        $order->forceFill([
            'cost_settlement_status' => $status->value,
            'cost_settlement_classification' => $classification->value,
        ])->save();
    }

    private function settlementStatusEnum(mixed $status): ProductionCostSettlementStatus
    {
        if ($status instanceof ProductionCostSettlementStatus) {
            return $status;
        }

        return ProductionCostSettlementStatus::tryFrom((string) $status)
            ?? ProductionCostSettlementStatus::Pending;
    }
}
