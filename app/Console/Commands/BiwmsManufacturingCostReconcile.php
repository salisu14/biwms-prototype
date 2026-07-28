<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\ProductionOutputCostAllocation;
use App\Models\ValueEntry;
use App\Services\Manufacturing\ProductionCostSummaryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:manufacturing-cost-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--production-order= : Limit diagnostics to one production order ID or document number} {--export= : Write the JSON report to a file path}')]
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

        $report = [
            'filters' => [
                'production_order' => $productionOrderFilter,
            ],
            'findings' => [
                'consumption_without_value_entries' => $this->productionItemEntriesWithoutValueEntries(ItemLedgerEntryType::CONSUMPTION, $productionOrderFilter),
                'output_without_value_entries' => $this->productionItemEntriesWithoutValueEntries(ItemLedgerEntryType::OUTPUT, $productionOrderFilter),
                'capacity_without_value_entries' => $this->capacityEntriesWithoutValueEntries($productionOrderFilter),
                'manufacturing_value_entries_not_gl_posted' => $this->manufacturingValueEntriesNotGlPosted($productionOrderFilter),
                'unsupported_manufacturing_cost_components' => $this->unsupportedManufacturingCostComponents($productionOrderFilter),
                'duplicate_capacity_postings' => $this->duplicateCapacityPostings($productionOrderFilter),
                'duplicate_output_allocations' => $this->duplicateOutputAllocations($productionOrderFilter),
                'output_cost_overallocated' => $this->outputCostOverallocated($productionOrderFilter),
                'finished_orders_with_unallocated_cost' => $this->finishedOrdersWithUnallocatedCost($productionOrderFilter),
                'finished_orders_without_cost_settlement' => $this->finishedOrdersWithoutCostSettlement($productionOrderFilter),
                'settled_orders_with_open_wip' => $this->settledOrdersWithOpenWip($productionOrderFilter),
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
