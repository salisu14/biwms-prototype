<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionCostSettlementStatus;
use App\Enums\ProductionHierarchyNodeType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\ProductionOutputCostAllocation;
use App\Models\ProductionVarianceCalculation;
use App\Models\ValueEntry;
use App\Services\Manufacturing\ProductionCostSummaryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:manufacturing-cost-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--production-order= : Limit diagnostics to one production order ID or document number} {--item= : Limit diagnostics to one item number/code} {--export= : Write the JSON report to a file path}')]
#[Description('Report BIWMS manufacturing costing, WIP, output allocation, and Value Entry consistency issues.')]
class BiwmsManufacturingCostReconcile extends Command
{
    public function __construct(
        private readonly ProductionCostSummaryService $summaryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $productionOrderFilter = $this->option('production-order');
        $itemFilter = $this->option('item');

        $report = [
            'filters' => [
                'production_order' => $productionOrderFilter,
                'item' => $itemFilter,
            ],
            'findings' => [
                'consumption_without_value_entries' => $this->productionItemEntriesWithoutValueEntries(ItemLedgerEntryType::CONSUMPTION, $productionOrderFilter),
                'output_without_value_entries' => $this->productionItemEntriesWithoutValueEntries(ItemLedgerEntryType::OUTPUT, $productionOrderFilter),
                'capacity_without_value_entries' => $this->capacityEntriesWithoutValueEntries($productionOrderFilter),
                'expected_manufacturing_cost_missing' => $this->expectedManufacturingCostMissing($productionOrderFilter),
                'expected_material_cost_uncleared' => $this->unclearedExpectedCost($productionOrderFilter, 'expected_direct_material', 'expected_material_cost_uncleared'),
                'expected_capacity_cost_uncleared' => $this->unclearedExpectedCost($productionOrderFilter, 'expected_direct_capacity', 'expected_capacity_cost_uncleared'),
                'expected_overhead_cost_uncleared' => $this->unclearedExpectedCost($productionOrderFilter, 'expected_capacity_overhead', 'expected_overhead_cost_uncleared'),
                'expected_output_cost_uncleared' => $this->unclearedExpectedCost($productionOrderFilter, 'expected_output', 'expected_output_cost_uncleared'),
                'actual_material_cost_missing' => $this->actualCostMissing($productionOrderFilter, 'direct_material', 'actual_material_cost_missing'),
                'actual_capacity_cost_missing' => $this->actualCostMissing($productionOrderFilter, 'direct_capacity', 'actual_capacity_cost_missing'),
                'actual_output_cost_missing' => $this->actualCostMissing($productionOrderFilter, 'output', 'actual_output_cost_missing'),
                'material_price_variance_mismatch' => $this->varianceMismatch($productionOrderFilter, 'material_price', 'material_price_variance_mismatch'),
                'material_quantity_variance_mismatch' => $this->varianceMismatch($productionOrderFilter, 'material_quantity', 'material_quantity_variance_mismatch'),
                'capacity_rate_variance_mismatch' => $this->varianceMismatch($productionOrderFilter, 'capacity_rate', 'capacity_rate_variance_mismatch'),
                'capacity_efficiency_variance_mismatch' => $this->varianceMismatch($productionOrderFilter, 'capacity_efficiency', 'capacity_efficiency_variance_mismatch'),
                'capacity_overhead_variance_mismatch' => $this->varianceMismatch($productionOrderFilter, 'capacity_overhead', 'capacity_overhead_variance_mismatch'),
                'standard_cost_variance_mismatch' => $this->varianceMismatch($productionOrderFilter, 'standard_cost', 'standard_cost_variance_mismatch'),
                'rounding_variance_outside_tolerance' => $this->varianceMismatch($productionOrderFilter, 'rounding', 'rounding_variance_outside_tolerance'),
                'late_material_cost_adjustment_pending' => $this->adjustmentRequiredOrders($productionOrderFilter, 'late_material_cost_adjustment_pending'),
                'late_capacity_cost_adjustment_pending' => [],
                'output_cost_adjustment_pending' => $this->outputCostAdjustmentPending($productionOrderFilter),
                'downstream_cost_adjustment_pending' => [],
                'settled_order_adjustment_required' => $this->adjustmentRequiredOrders($productionOrderFilter, 'settled_order_adjustment_required'),
                'completed_adjustment_not_resettled' => $this->completedAdjustmentNotResettled($productionOrderFilter),
                'duplicate_expected_cost' => $this->duplicateValueEntries($productionOrderFilter, 'expected', 'duplicate_expected_cost'),
                'duplicate_expected_cost_clearing' => $this->duplicateValueEntries($productionOrderFilter, 'clearing', 'duplicate_expected_cost_clearing'),
                'duplicate_variance_entry' => $this->duplicateValueEntries($productionOrderFilter, 'variance', 'duplicate_variance_entry'),
                'duplicate_cost_propagation' => $this->duplicateValueEntries($productionOrderFilter, 'adjustment', 'duplicate_cost_propagation'),
                'closed_costing_period_violation' => [],
                'missing_manufacturing_posting_account' => $this->missingManufacturingPostingAccount($productionOrderFilter),
                'unsupported_variance_type' => $this->unsupportedVarianceType($productionOrderFilter),
                'manufacturing_value_entries_not_gl_posted' => $this->manufacturingValueEntriesNotGlPosted($productionOrderFilter),
                'manufacturing_gl_without_value_entry' => [],
                'unsupported_manufacturing_cost_components' => $this->unsupportedManufacturingCostComponents($productionOrderFilter),
                'reversal_chain_broken' => $this->reversalChainBroken($productionOrderFilter),
                'settlement_history_mismatch' => $this->settlementHistoryMismatch($productionOrderFilter),
                'production_cost_summary_mismatch' => $this->productionCostSummaryMismatch($productionOrderFilter),
                'duplicate_capacity_postings' => $this->duplicateCapacityPostings($productionOrderFilter),
                'duplicate_output_allocations' => $this->duplicateOutputAllocations($productionOrderFilter),
                'output_cost_overallocated' => $this->outputCostOverallocated($productionOrderFilter),
                'finished_orders_with_unallocated_cost' => $this->finishedOrdersWithUnallocatedCost($productionOrderFilter),
                'finished_orders_without_cost_settlement' => $this->finishedOrdersWithoutCostSettlement($productionOrderFilter),
                'settled_orders_with_open_wip' => $this->settledOrdersWithOpenWip($productionOrderFilter),
                'phase_2a2_manufactured_node_without_child_order' => $this->manufacturedNodesWithoutChildOrders($productionOrderFilter),
                'phase_2a2_generated_child_without_supply_link' => $this->generatedChildOrdersWithoutSupplyLinks($productionOrderFilter),
                'phase_2a2_manufactured_component_without_reservation' => $this->manufacturedComponentsWithoutReservations($productionOrderFilter),
            ],
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Manufacturing Cost Reconciliation');
        $this->line('Mode: report-only. No production, value, or G/L entries were changed.');
        if ($productionOrderFilter) {
            $this->line("Filter: production-order={$productionOrderFilter}");
        }
        if ($itemFilter) {
            $this->line("Filter: item={$itemFilter}");
        }
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        $details = (bool) $this->option('details');

        foreach ($report['findings'] as $title => $findings) {
            $this->section(
                title: str_replace('_', ' ', ucfirst($title)),
                rows: $findings,
                details: $details,
                formatter: fn (array $finding): string => $this->formatFinding($finding),
            );
        }

        return self::SUCCESS;
    }

    private function expectedManufacturingCostMissing(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->whereIn('status', [ProductionOrderStatus::RELEASED, ProductionOrderStatus::FINISHED])
            ->whereDoesntHave('expectedCostSnapshots')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                ...$this->findingMetadata(
                    classification: 'expected_manufacturing_cost_missing',
                    severity: 'warning',
                    suggestedRemediation: 'Calculate expected manufacturing cost before final settlement so variance analysis has a historical baseline.',
                ),
            ])
            ->values()
            ->all();
    }

    private function unclearedExpectedCost(mixed $productionOrderFilter, string $component, string $classification): array
    {
        return $this->manufacturingValueEntryQuery($productionOrderFilter)
            ->where('expected_cost', true)
            ->where('value_entry_state', 'expected')
            ->where('cost_component', $component)
            ->whereRaw('ABS(COALESCE(cost_amount_expected, 0)) > 0.0001')
            ->limit(250)
            ->get()
            ->map(fn (ValueEntry $entry): array => [
                'production_order_no' => $entry->production_order_no,
                'value_entry_id' => $entry->id,
                'cost_component' => $entry->cost_component,
                'uncleared_amount' => round((float) $entry->cost_amount_expected, 4),
                ...$this->findingMetadata(
                    classification: $classification,
                    severity: 'warning',
                    suggestedRemediation: 'Run expected-cost clearing against matching actual manufacturing Value Entries; preserve the original expected entry.',
                ),
            ])
            ->values()
            ->all();
    }

    private function actualCostMissing(mixed $productionOrderFilter, string $component, string $classification): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->whereIn('status', [ProductionOrderStatus::RELEASED, ProductionOrderStatus::FINISHED])
            ->get()
            ->filter(fn (ProductionOrder $order): bool => ! $this->manufacturingValueEntryQuery($order->document_number)
                ->where('expected_cost', false)
                ->where('cost_component', $component)
                ->exists())
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                ...$this->findingMetadata(
                    classification: $classification,
                    severity: 'warning',
                    suggestedRemediation: 'Verify whether this production order legitimately has no '.$component.' actual cost or post the missing manufacturing source document.',
                ),
            ])
            ->values()
            ->all();
    }

    private function varianceMismatch(mixed $productionOrderFilter, string $varianceType, string $classification): array
    {
        return ProductionVarianceCalculation::query()
            ->where('variance_type', $varianceType)
            ->whereNull('posted_value_entry_id')
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->whereHas('productionOrder', function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter);

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            })
            ->limit(250)
            ->get()
            ->map(fn (ProductionVarianceCalculation $variance): array => [
                'production_order_id' => $variance->production_order_id,
                'variance_calculation_id' => $variance->id,
                'variance_amount' => round((float) $variance->variance_amount, 4),
                ...$this->findingMetadata(
                    classification: $classification,
                    severity: 'warning',
                    suggestedRemediation: 'Review and post eligible variance through ProductionVarianceValueEntryService; do not write G/L directly.',
                ),
            ])
            ->values()
            ->all();
    }

    private function adjustmentRequiredOrders(mixed $productionOrderFilter, string $classification): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->where('cost_settlement_status', ProductionCostSettlementStatus::AdjustmentRequired->value)
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                'settlement_classification' => $order->cost_settlement_classification?->value ?? $order->cost_settlement_classification,
                ...$this->findingMetadata(
                    classification: $classification,
                    severity: 'warning',
                    suggestedRemediation: 'Run the controlled production cost-adjustment workflow and resettle the order when all append-only adjustments are posted.',
                ),
            ])
            ->values()
            ->all();
    }

    private function outputCostAdjustmentPending(mixed $productionOrderFilter): array
    {
        return $this->manufacturingValueEntryQuery($productionOrderFilter)
            ->where('value_entry_state', 'adjustment')
            ->where('gl_posted', false)
            ->limit(250)
            ->get()
            ->map(fn (ValueEntry $entry): array => [
                'production_order_no' => $entry->production_order_no,
                'value_entry_id' => $entry->id,
                'amount' => round((float) $entry->cost_amount_actual, 4),
                ...$this->findingMetadata(
                    classification: 'output_cost_adjustment_pending',
                    severity: 'warning',
                    suggestedRemediation: 'Post the adjustment Value Entry through ValueEntryAccountingOrchestrator, then refresh production settlement.',
                ),
            ])
            ->values()
            ->all();
    }

    private function completedAdjustmentNotResettled(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->where('cost_settlement_status', ProductionCostSettlementStatus::AdjustmentRequired->value)
            ->whereHas('outputCostAllocations')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                ...$this->findingMetadata(
                    classification: 'completed_adjustment_not_resettled',
                    severity: 'warning',
                    suggestedRemediation: 'After all adjustment Value Entries are posted, rerun production cost settlement to return the order to settled.',
                ),
            ])
            ->values()
            ->all();
    }

    private function duplicateValueEntries(mixed $productionOrderFilter, string $state, string $classification): array
    {
        return $this->manufacturingValueEntryQuery($productionOrderFilter)
            ->selectRaw('idempotency_key, COUNT(*) as entry_count')
            ->where('value_entry_state', $state)
            ->whereNotNull('idempotency_key')
            ->groupBy('idempotency_key')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($entry): array => [
                'idempotency_key' => $entry->idempotency_key,
                'entry_count' => (int) $entry->entry_count,
                ...$this->findingMetadata(
                    classification: $classification,
                    severity: 'critical',
                    suggestedRemediation: 'Investigate duplicate idempotency identity usage and correct only through append-only reversals.',
                ),
            ])
            ->values()
            ->all();
    }

    private function missingManufacturingPostingAccount(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->get()
            ->filter(fn (ProductionOrder $order): bool => ! $order->getPostingSetup())
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                ...$this->findingMetadata(
                    classification: 'missing_manufacturing_posting_account',
                    severity: 'critical',
                    suggestedRemediation: 'Configure General Posting Setup for the order posting groups before posting or settling manufacturing cost.',
                ),
            ])
            ->values()
            ->all();
    }

    private function unsupportedVarianceType(mixed $productionOrderFilter): array
    {
        $supported = ['material_price', 'material_quantity', 'capacity_rate', 'capacity_efficiency', 'capacity_overhead', 'standard_cost', 'yield', 'rounding', 'controlled_other'];

        return ProductionVarianceCalculation::query()
            ->whereNotIn('variance_type', $supported)
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->whereHas('productionOrder', function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter);

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            })
            ->get()
            ->map(fn (ProductionVarianceCalculation $variance): array => [
                'production_order_id' => $variance->production_order_id,
                'variance_type' => $variance->variance_type?->value ?? $variance->getRawOriginal('variance_type'),
                ...$this->findingMetadata(
                    classification: 'unsupported_variance_type',
                    severity: 'critical',
                    suggestedRemediation: 'Map unsupported variance types to a stable enum before posting.',
                ),
            ])
            ->values()
            ->all();
    }

    private function reversalChainBroken(mixed $productionOrderFilter): array
    {
        return $this->manufacturingValueEntryQuery($productionOrderFilter)
            ->whereNotNull('reversal_of_value_entry_id')
            ->get()
            ->filter(fn (ValueEntry $entry): bool => ! ValueEntry::query()->whereKey($entry->reversal_of_value_entry_id)->exists())
            ->map(fn (ValueEntry $entry): array => [
                'value_entry_id' => $entry->id,
                'reversal_of_value_entry_id' => $entry->reversal_of_value_entry_id,
                ...$this->findingMetadata(
                    classification: 'reversal_chain_broken',
                    severity: 'critical',
                    suggestedRemediation: 'Investigate missing original Value Entry; do not delete or rewrite reversal history.',
                ),
            ])
            ->values()
            ->all();
    }

    private function settlementHistoryMismatch(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->whereNotNull('cost_settled_at')
            ->whereDoesntHave('outputCostAllocations')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                ...$this->findingMetadata(
                    classification: 'settlement_history_mismatch',
                    severity: 'critical',
                    suggestedRemediation: 'Review settlement history and output allocation records; settled orders should have traceable output allocation.',
                ),
            ])
            ->values()
            ->all();
    }

    private function productionCostSummaryMismatch(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->get()
            ->map(function (ProductionOrder $order): ?array {
                $summary = $this->summaryService->summarize($order);
                $difference = round((float) $summary['total_accumulated_cost'] - (float) $summary['allocated_output_cost'] - (float) $summary['unallocated_cost'], 4);

                if (abs($difference) <= 0.0001) {
                    return null;
                }

                return [
                    'production_order_id' => $order->id,
                    'production_order_no' => $order->document_number,
                    'difference' => $difference,
                    ...$this->findingMetadata(
                        classification: 'production_cost_summary_mismatch',
                        severity: 'critical',
                        suggestedRemediation: 'Rebuild the production costing read model from Value Entries and output allocations; do not use cached order totals as authority.',
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productionItemEntriesWithoutValueEntries(ItemLedgerEntryType $entryType, mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->with(['itemLedgerEntries.item'])
            ->get()
            ->flatMap(function (ProductionOrder $order) use ($entryType): array {
                return $order->itemLedgerEntries
                    ->where('entry_type', $entryType)
                    ->filter(fn ($entry): bool => ! ValueEntry::query()
                        ->where('item_ledger_entry_no', $entry->entry_number)
                        ->where('document_no', $entry->document_number)
                        ->where('document_line_no', $entry->document_line_number)
                        ->exists())
                    ->map(fn ($entry): array => [
                        'production_order_id' => $order->id,
                        'production_order_no' => $order->document_number,
                        'item_ledger_entry_id' => $entry->id,
                        'entry_number' => $entry->entry_number,
                        'entry_type' => $entryType->value,
                        'item_id' => $entry->item_id,
                        'item_no' => $entry->item?->item_code,
                        'quantity' => round((float) $entry->quantity, 8),
                        'cost_amount_actual' => round((float) $entry->cost_amount_actual, 4),
                        ...$this->findingMetadata(
                            classification: $entryType === ItemLedgerEntryType::CONSUMPTION
                                ? 'consumption_value_missing'
                                : 'output_value_missing',
                            severity: 'critical',
                            suggestedRemediation: 'Trace the source production posting and create any correction through a controlled repost/reversal path; do not edit ledger history directly.',
                        ),
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function capacityEntriesWithoutValueEntries(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->with('capacityLedgerEntries')
            ->get()
            ->flatMap(function (ProductionOrder $order): array {
                return $order->capacityLedgerEntries
                    ->filter(fn (CapacityLedgerEntry $entry): bool => ! ValueEntry::query()
                        ->where('source_type', CapacityLedgerEntry::class)
                        ->where('source_id', $entry->id)
                        ->exists())
                    ->map(fn (CapacityLedgerEntry $entry): array => [
                        'production_order_id' => $order->id,
                        'production_order_no' => $order->document_number,
                        'capacity_ledger_entry_id' => $entry->id,
                        'routing_line_id' => $entry->routing_line_id,
                        'direct_cost' => round((float) $entry->direct_cost, 4),
                        'overhead_cost' => round((float) $entry->overhead_cost, 4),
                        'total_cost' => round((float) $entry->total_cost, 4),
                        ...$this->findingMetadata(
                            classification: 'capacity_value_missing',
                            severity: 'critical',
                            suggestedRemediation: 'Re-run the controlled capacity value-entry creation/posting path for the affected production order, then verify G/L through Value Entry ownership.',
                        ),
                    ])
                    ->values()
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manufacturingValueEntriesNotGlPosted(mixed $productionOrderFilter): array
    {
        return $this->manufacturingValueEntryQuery($productionOrderFilter)
            ->where('gl_posted', false)
            ->whereRaw('ABS(COALESCE(cost_amount_actual, 0)) + ABS(COALESCE(cost_amount_expected, 0)) > 0.0001')
            ->limit(250)
            ->get()
            ->map(fn (ValueEntry $entry): array => [
                'production_order_no' => $entry->production_order_no,
                'value_entry_id' => $entry->id,
                'value_entry_no' => $entry->entry_no,
                'document_no' => $entry->document_no,
                'cost_component' => $entry->cost_component,
                'cost_amount_actual' => round((float) $entry->cost_amount_actual, 4),
                ...$this->findingMetadata(
                    classification: 'manufacturing_gl_missing',
                    severity: 'critical',
                    suggestedRemediation: 'Post manufacturing inventory value to G/L through ValueEntryAccountingOrchestrator; do not create source-module duplicate G/L rows.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unsupportedManufacturingCostComponents(mixed $productionOrderFilter): array
    {
        $supportedComponents = array_map(
            fn (ManufacturingCostComponent $component): string => $component->value,
            ManufacturingCostComponent::cases(),
        );

        return $this->manufacturingValueEntryQuery($productionOrderFilter)
            ->whereNotNull('cost_component')
            ->get()
            ->filter(fn (ValueEntry $entry): bool => ! in_array($this->normalizedCostComponent($entry), $supportedComponents, true))
            ->map(fn (ValueEntry $entry): array => [
                'production_order_no' => $entry->production_order_no,
                'value_entry_id' => $entry->id,
                'value_entry_no' => $entry->entry_no,
                'document_no' => $entry->document_no,
                'cost_component' => $entry->cost_component,
                ...$this->findingMetadata(
                    classification: 'unsupported_manufacturing_cost_component',
                    severity: 'critical',
                    suggestedRemediation: 'Map the legacy cost component explicitly before posting to G/L; do not allow unknown manufacturing cost components to route by description.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function outputCostOverallocated(mixed $productionOrderFilter): array
    {
        return $this->productionOrderQuery($productionOrderFilter)
            ->get()
            ->map(function (ProductionOrder $order): ?array {
                $summary = $this->summaryService->summarize($order);
                $difference = round((float) $summary['allocated_output_cost'] - (float) $summary['total_accumulated_cost'], 4);

                if ($difference <= 0.0001) {
                    return null;
                }

                return [
                    'production_order_id' => $order->id,
                    'production_order_no' => $order->document_number,
                    'total_accumulated_cost' => round((float) $summary['total_accumulated_cost'], 4),
                    'allocated_output_cost' => round((float) $summary['allocated_output_cost'], 4),
                    'difference' => $difference,
                    ...$this->findingMetadata(
                        classification: 'duplicate_output_allocation',
                        severity: 'critical',
                        suggestedRemediation: 'Review output allocations against material/capacity/overhead Value Entries and correct through a manufacturing cost adjustment.',
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function duplicateCapacityPostings(mixed $productionOrderFilter): array
    {
        return CapacityLedgerEntry::query()
            ->selectRaw('idempotency_key, production_order_id, routing_line_id, COUNT(*) as entry_count, SUM(total_cost) as total_cost')
            ->whereNotNull('idempotency_key')
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->whereHas('productionOrder', function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter);

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            })
            ->groupBy('idempotency_key', 'production_order_id', 'routing_line_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => [
                'production_order_id' => $entry->production_order_id,
                'routing_line_id' => $entry->routing_line_id,
                'idempotency_key' => $entry->idempotency_key,
                'entry_count' => (int) $entry->entry_count,
                'total_cost' => round((float) $entry->total_cost, 4),
                ...$this->findingMetadata(
                    classification: 'duplicate_capacity_posting',
                    severity: 'critical',
                    suggestedRemediation: 'Trace duplicate capacity entries to the source document line and correct through an append-only reversal; do not delete ledger history.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function duplicateOutputAllocations(mixed $productionOrderFilter): array
    {
        return ProductionOutputCostAllocation::query()
            ->selectRaw('source_identity_key, production_order_id, output_item_ledger_entry_id, COUNT(*) as allocation_count, SUM(allocated_total_cost) as allocated_total_cost')
            ->whereNotNull('source_identity_key')
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->whereHas('productionOrder', function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter);

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            })
            ->groupBy('source_identity_key', 'production_order_id', 'output_item_ledger_entry_id')
            ->havingRaw('COUNT(*) > 1')
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => [
                'production_order_id' => $entry->production_order_id,
                'output_item_ledger_entry_id' => $entry->output_item_ledger_entry_id,
                'source_identity_key' => $entry->source_identity_key,
                'allocation_count' => (int) $entry->allocation_count,
                'allocated_total_cost' => round((float) $entry->allocated_total_cost, 4),
                ...$this->findingMetadata(
                    classification: 'duplicate_output_allocation',
                    severity: 'critical',
                    suggestedRemediation: 'Review duplicate output allocations and correct through explicit reversal/allocation adjustment records.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function finishedOrdersWithUnallocatedCost(mixed $productionOrderFilter): array
    {
        if (! Schema::hasColumn('production_orders', 'cost_settled_at')) {
            return [];
        }

        return $this->productionOrderQuery($productionOrderFilter)
            ->where('status', ProductionOrderStatus::FINISHED)
            ->whereNull('cost_settled_at')
            ->get()
            ->map(function (ProductionOrder $order): ?array {
                $summary = $this->summaryService->summarize($order);
                $unallocated = round((float) $summary['unallocated_cost'], 4);

                if (abs($unallocated) <= 0.01) {
                    return null;
                }

                return [
                    'production_order_id' => $order->id,
                    'production_order_no' => $order->document_number,
                    'unallocated_cost' => $unallocated,
                    'total_accumulated_cost' => round((float) $summary['total_accumulated_cost'], 4),
                    'allocated_output_cost' => round((float) $summary['allocated_output_cost'], 4),
                    ...$this->findingMetadata(
                        classification: 'unallocated_actual_cost',
                        severity: 'warning',
                        suggestedRemediation: 'Run cost settlement for the finished order or investigate missing output allocation/variance Value Entries.',
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function finishedOrdersWithoutCostSettlement(mixed $productionOrderFilter): array
    {
        if (! Schema::hasColumn('production_orders', 'cost_settled_at')) {
            return [];
        }

        return $this->productionOrderQuery($productionOrderFilter)
            ->where('status', ProductionOrderStatus::FINISHED)
            ->whereNull('cost_settled_at')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                'status' => $order->status?->value ?? (string) $order->status,
                ...$this->findingMetadata(
                    classification: 'finished_order_not_cost_settled',
                    severity: 'warning',
                    suggestedRemediation: 'Run the production finish/cost settlement path so output allocations and residual variance are captured by Value Entries.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function settledOrdersWithOpenWip(mixed $productionOrderFilter): array
    {
        if (! Schema::hasColumn('production_orders', 'cost_settled_at')) {
            return [];
        }

        return $this->productionOrderQuery($productionOrderFilter)
            ->whereNotNull('cost_settled_at')
            ->get()
            ->map(function (ProductionOrder $order): ?array {
                $summary = $this->summaryService->summarize($order);
                $netWip = round((float) $summary['total_accumulated_cost'] - (float) $summary['allocated_output_cost'] - abs((float) $summary['variance']), 4);

                if (abs($netWip) <= 0.01) {
                    return null;
                }

                return [
                    'production_order_id' => $order->id,
                    'production_order_no' => $order->document_number,
                    'net_wip_amount' => $netWip,
                    'variance_amount' => round((float) $summary['variance'], 4),
                    ...$this->findingMetadata(
                        classification: 'settled_order_open_wip',
                        severity: 'critical',
                        suggestedRemediation: 'Investigate missing output allocation or variance Value Entry; settlement should leave no unexplained WIP for a finished single-level order.',
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function productionOrderQuery(mixed $productionOrderFilter): Builder
    {
        return ProductionOrder::query()
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->where(function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter);

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manufacturedNodesWithoutChildOrders(mixed $productionOrderFilter): array
    {
        return ProductionHierarchyNode::query()
            ->where('node_type', ProductionHierarchyNodeType::ManufacturedComponent->value)
            ->whereNull('production_order_id')
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->whereHas('rootProductionOrder', function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter);

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            })
            ->limit(250)
            ->get()
            ->map(fn (ProductionHierarchyNode $node): array => [
                'production_hierarchy_node_id' => $node->id,
                'root_production_order_id' => $node->root_production_order_id,
                'node_path' => $node->node_path,
                'item_no' => $node->item_no,
                'required_quantity_base' => round((float) $node->required_quantity_base, 8),
                ...$this->findingMetadata(
                    classification: 'phase_2a2_manufactured_node_without_child_order',
                    severity: 'critical',
                    suggestedRemediation: 'Run multi-level production planning again after confirming the child item has a certified BOM and number series setup.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generatedChildOrdersWithoutSupplyLinks(mixed $productionOrderFilter): array
    {
        return ProductionOrder::query()
            ->whereNotNull('source_production_order_component_id')
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->where(function (Builder $query) use ($filter): void {
                    $query->where('document_number', (string) $filter)
                        ->orWhereHas('rootProductionOrder', function (Builder $query) use ($filter): void {
                            $query->where('document_number', (string) $filter);

                            if (is_numeric($filter)) {
                                $query->orWhere('id', (int) $filter);
                            }
                        });

                    if (is_numeric($filter)) {
                        $query->orWhere('id', (int) $filter);
                    }
                });
            })
            ->whereDoesntHave('supplyLinksAsChild', function (Builder $query): void {
                $query->where('supply_type', ProductionSupplyType::GeneratedChildOrder->value)
                    ->where('status', '!=', ProductionSupplyLinkStatus::Cancelled->value);
            })
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
                'source_production_order_component_id' => $order->source_production_order_component_id,
                ...$this->findingMetadata(
                    classification: 'phase_2a2_generated_child_without_supply_link',
                    severity: 'critical',
                    suggestedRemediation: 'Rebuild the Phase 2A.2 hierarchy from the root order so the child supply link is recreated idempotently.',
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manufacturedComponentsWithoutReservations(mixed $productionOrderFilter): array
    {
        return ProductionOrderComponent::query()
            ->where('is_manufactured_requirement', true)
            ->whereDoesntHave('materialReservations', function (Builder $query): void {
                $query->where('reservation_type', ProductionReservationType::ChildOutput->value)
                    ->where('status', '!=', ProductionReservationStatus::Cancelled->value);
            })
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->where(function (Builder $query) use ($filter): void {
                    $query->whereHas('productionOrder.rootProductionOrder', function (Builder $query) use ($filter): void {
                        $query->where('document_number', (string) $filter);

                        if (is_numeric($filter)) {
                            $query->orWhere('id', (int) $filter);
                        }
                    })->orWhereHas('productionOrder', function (Builder $query) use ($filter): void {
                        $query->where('document_number', (string) $filter);

                        if (is_numeric($filter)) {
                            $query->orWhere('id', (int) $filter);
                        }
                    });
                });
            })
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrderComponent $component): array => [
                'production_order_component_id' => $component->id,
                'production_order_id' => $component->production_order_id,
                'item_id' => $component->item_id,
                'expected_quantity_base' => round((float) $component->expected_quantity_base, 8),
                ...$this->findingMetadata(
                    classification: 'phase_2a2_manufactured_component_without_reservation',
                    severity: 'warning',
                    suggestedRemediation: 'Re-run multi-level planning so the manufactured component demand is linked to child output reservation.',
                ),
            ])
            ->values()
            ->all();
    }

    private function manufacturingValueEntryQuery(mixed $productionOrderFilter): Builder
    {
        return ValueEntry::query()
            ->where(function (Builder $query): void {
                $query->where('source_module', 'manufacturing')
                    ->orWhereNotNull('production_order_no')
                    ->orWhereIn('cost_component', array_map(
                        fn (ManufacturingCostComponent $component): string => $component->value,
                        ManufacturingCostComponent::cases(),
                    ));
            })
            ->when($productionOrderFilter, function (Builder $query, mixed $filter): void {
                $query->where(function (Builder $query) use ($filter): void {
                    $query->where('production_order_no', (string) $filter)
                        ->orWhere('document_no', (string) $filter);

                    if (is_numeric($filter)) {
                        $documentNumber = ProductionOrder::query()->whereKey((int) $filter)->value('document_number');

                        if ($documentNumber) {
                            $query->orWhere('production_order_no', $documentNumber)
                                ->orWhere('document_no', $documentNumber);
                        }
                    }
                });
            });
    }

    /**
     * @return array{classification: string, severity: string, suggested_remediation: string}
     */
    private function findingMetadata(string $classification, string $severity, string $suggestedRemediation): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'suggested_remediation' => $suggestedRemediation,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function exportReport(array $report, string $path): void
    {
        $absolutePath = base_path($path);
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function section(string $title, array $rows, bool $details, callable $formatter): void
    {
        $count = count($rows);

        if ($count === 0) {
            $this->line("{$title}: OK");

            return;
        }

        $this->warn("{$title}: {$count} finding(s)");

        if (! $details) {
            return;
        }

        foreach ($rows as $row) {
            $this->line('  - '.$formatter($row));
        }
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private function formatFinding(array $finding): string
    {
        $summary = collect($finding)
            ->except(['suggested_remediation'])
            ->map(fn (mixed $value, string $key): string => $key.'='.$this->stringify($value))
            ->implode(' ');

        return $summary.' remediation="'.$finding['suggested_remediation'].'"';
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function normalizedCostComponent(ValueEntry $valueEntry): string
    {
        return match (strtolower((string) $valueEntry->cost_component)) {
            'material' => ManufacturingCostComponent::DirectMaterial->value,
            'capacity' => ManufacturingCostComponent::DirectCapacity->value,
            'overhead' => ManufacturingCostComponent::CapacityOverhead->value,
            default => strtolower((string) $valueEntry->cost_component),
        };
    }
}
