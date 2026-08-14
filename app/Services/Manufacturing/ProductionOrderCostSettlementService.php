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
use App\Services\Inventory\ExpectedCostClearingService;
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
        private readonly ExpectedManufacturingCostService $expectedCostService,
        private readonly ExpectedCostClearingService $expectedCostClearingService,
        private readonly ProductionVarianceCalculationService $varianceCalculationService,
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

            if ($lockedOrder->cost_settled_at && ! $this->requiresResettlement($lockedOrder)) {
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
            if ($readiness['classification'] === ProductionCostSettlementClassification::PendingExpectedCost) {
                $this->expectedCostService->calculate($lockedOrder, null, $postingDate, $userId);
                $readiness = $this->evaluateReadiness($lockedOrder->fresh());
            }

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

            $this->clearExpectedCosts($lockedOrder, $userId);

            $summary = $this->summaryService->summarize($lockedOrder);
            $variance = round((float) $summary['total_accumulated_cost'] - (float) $summary['allocated_output_cost'], 4);
            $varianceClassification = $this->classifySettlementDifference($lockedOrder, $summary, $variance);
            $varianceEntry = null;

            if ($varianceClassification === ProductionCostSettlementClassification::TrueProductionVariance && abs($variance) > 0.0001) {
                $calculations = $this->varianceCalculationService->calculate($lockedOrder, postingDate: $postingDate, userId: $userId);
                foreach ($calculations as $calculation) {
                    $varianceEntry = $this->varianceService->postCalculation($calculation, $userId) ?? $varianceEntry;
                }

                if (! $varianceEntry) {
                    $varianceEntry = $this->varianceService->recordVariance(
                        order: $lockedOrder,
                        varianceAmount: $variance,
                        component: $this->isStandardCosting($lockedOrder)
                            ? ManufacturingCostComponent::StandardCostVariance
                            : ManufacturingCostComponent::Variance,
                        postingDate: $postingDate,
                        userId: $userId,
                        reason: 'Production order final cost settlement',
                    );
                }
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

        if (! $order->expectedCostSnapshots()->exists()) {
            $reasons[] = ProductionCostSettlementClassification::PendingExpectedCost->value;
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

        if ($this->hasIncompleteRoutingCapacity($order)) {
            $reasons[] = ProductionCostSettlementClassification::RequiredCapacityNotPosted->value;
        }

        $unresolvedJournalLines = ProductionJournalLine::query()
            ->where('production_order_id', $order->id)
            ->whereNotIn('line_status', [JournalLineStatus::POSTED->value])
            ->exists();

        if ($unresolvedJournalLines) {
            $reasons[] = ProductionCostSettlementClassification::UnresolvedProductionJournalLines->value;
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

    private function clearExpectedCosts(ProductionOrder $order, int $userId): void
    {
        $actualEntries = $this->manufacturingValueEntries($order)
            ->where('expected_cost', false)
            ->whereIn('cost_component', [
                ManufacturingCostComponent::DirectMaterial->value,
                ManufacturingCostComponent::DirectCapacity->value,
                ManufacturingCostComponent::CapacityOverhead->value,
                ManufacturingCostComponent::Output->value,
                ManufacturingCostComponent::CostAdjustment->value,
                'material',
                'capacity',
                'overhead',
                'output',
            ])
            ->get();

        $expectedEntries = $this->manufacturingValueEntries($order)
            ->where('expected_cost', true)
            ->where('value_entry_state', 'expected')
            ->whereRaw('ABS(COALESCE(cost_amount_expected, 0)) > 0.0001')
            ->get();

        foreach ($expectedEntries as $expectedEntry) {
            $actualEntry = $actualEntries->first(fn (ValueEntry $entry): bool => $this->actualClearsExpected($expectedEntry, $entry));
            if (! $actualEntry) {
                continue;
            }

            $outstandingExpectedAmount = $this->unclearedExpectedAmount($expectedEntry);
            if ($outstandingExpectedAmount <= DecimalTolerance::AMOUNT) {
                continue;
            }

            $this->expectedCostClearingService->clearForActualManufacturingCost(
                expectedEntry: $expectedEntry,
                actualEntry: $actualEntry,
                quantityBase: $this->quantityBaseForExpectedClearing($expectedEntry, $actualEntry),
                amountToClear: min($outstandingExpectedAmount, abs((float) $actualEntry->cost_amount_actual)),
                userId: $userId,
            );
        }
    }

    private function actualClearsExpected(ValueEntry $expectedEntry, ValueEntry $actualEntry): bool
    {
        if (! $this->expectedAndActualReferToSameManufacturingLine($expectedEntry, $actualEntry)) {
            return false;
        }

        return match ((string) $expectedEntry->cost_component) {
            ManufacturingCostComponent::ExpectedDirectMaterial->value => in_array((string) $actualEntry->cost_component, [ManufacturingCostComponent::DirectMaterial->value, ManufacturingCostComponent::CostAdjustment->value, 'material'], true),
            ManufacturingCostComponent::ExpectedDirectCapacity->value => in_array((string) $actualEntry->cost_component, [ManufacturingCostComponent::DirectCapacity->value, 'capacity'], true),
            ManufacturingCostComponent::ExpectedCapacityOverhead->value => in_array((string) $actualEntry->cost_component, [ManufacturingCostComponent::CapacityOverhead->value, 'overhead'], true),
            ManufacturingCostComponent::ExpectedOutput->value => (string) $actualEntry->cost_component === ManufacturingCostComponent::Output->value,
            default => false,
        };
    }

    private function hasIncompleteRoutingCapacity(ProductionOrder $order): bool
    {
        return $order->routingLines()
            ->withCount('capacityLedgerEntries')
            ->get()
            ->contains(function ($routingLine): bool {
                if ((string) $routingLine->status !== 'COMPLETED') {
                    return true;
                }

                $requiresCapacityEvidence = abs((float) $routingLine->setup_time) > DecimalTolerance::QUANTITY
                    || abs((float) $routingLine->run_time) > DecimalTolerance::QUANTITY
                    || abs((float) $routingLine->actual_setup_time) > DecimalTolerance::QUANTITY
                    || abs((float) $routingLine->actual_run_time) > DecimalTolerance::QUANTITY;

                return $requiresCapacityEvidence && (int) $routingLine->capacity_ledger_entries_count === 0;
            });
    }

    private function expectedAndActualReferToSameManufacturingLine(ValueEntry $expectedEntry, ValueEntry $actualEntry): bool
    {
        if ((string) $expectedEntry->production_order_no !== (string) $actualEntry->production_order_no) {
            return false;
        }

        if ((string) $expectedEntry->cost_component === ManufacturingCostComponent::ExpectedOutput->value) {
            return true;
        }

        foreach (['production_order_component_line_no', 'source_line_no', 'document_line_no'] as $attribute) {
            $expectedValue = $expectedEntry->{$attribute};
            $actualValue = $actualEntry->{$attribute};

            if (filled($expectedValue) && filled($actualValue)) {
                return (string) $expectedValue === (string) $actualValue;
            }
        }

        return filled($expectedEntry->item_no)
            && filled($actualEntry->item_no)
            && (string) $expectedEntry->item_no === (string) $actualEntry->item_no;
    }

    private function quantityBaseForExpectedClearing(ValueEntry $expectedEntry, ValueEntry $actualEntry): float
    {
        foreach ([$actualEntry->valued_quantity, $actualEntry->quantity, $expectedEntry->valued_quantity, $expectedEntry->quantity] as $quantity) {
            $absoluteQuantity = abs((float) $quantity);

            if ($absoluteQuantity > DecimalTolerance::QUANTITY) {
                return $absoluteQuantity;
            }
        }

        return 1.0;
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

        if ($this->unclearedExpectedCost($order) > DecimalTolerance::AMOUNT) {
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

    private function requiresResettlement(ProductionOrder $order): bool
    {
        $status = $this->settlementStatusEnum($order->cost_settlement_status);
        $summary = $this->summaryService->summarize($order);

        return $status === ProductionCostSettlementStatus::AdjustmentRequired
            || abs((float) $summary['unallocated_cost']) > DecimalTolerance::AMOUNT
            || abs((float) $summary['uncleared_expected_cost']) > DecimalTolerance::AMOUNT
            || abs((float) $summary['cost_not_posted_to_gl']) > DecimalTolerance::AMOUNT;
    }

    private function unclearedExpectedCost(ProductionOrder $order): float
    {
        return (float) $this->manufacturingValueEntries($order)
            ->where('expected_cost', true)
            ->where('value_entry_state', 'expected')
            ->get()
            ->sum(fn (ValueEntry $expectedEntry): float => $this->unclearedExpectedAmount($expectedEntry));
    }

    private function unclearedExpectedAmount(ValueEntry $expectedEntry): float
    {
        $clearedAmount = (float) ValueEntry::query()
            ->where('expected_cost', true)
            ->where('value_entry_state', 'clearing')
            ->where('reversal_of_value_entry_id', $expectedEntry->id)
            ->sum('cost_amount_expected');

        return abs(round((float) $expectedEntry->cost_amount_expected + $clearedAmount, 4));
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
