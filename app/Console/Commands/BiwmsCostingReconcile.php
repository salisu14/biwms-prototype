<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CostAdjustmentBatch;
use App\Models\CostingPeriod;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\ValueEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('biwms:costing-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path}')]
#[Description('Report BIWMS item application, expected-cost clearing, transfer in-transit, and cost adjustment consistency issues.')]
class BiwmsCostingReconcile extends Command
{
    public function handle(): int
    {
        $report = [
            'outbound_without_applications' => $this->outboundWithoutApplications(),
            'over_applied_item_ledger_entries' => $this->overAppliedItemLedgerEntries(),
            'application_quantity_mismatches' => $this->applicationQuantityMismatches(),
            'inbound_remaining_quantity_mismatches' => $this->inboundRemainingQuantityMismatches(),
            'value_entry_cost_mismatches' => $this->valueEntryCostMismatches(),
            'expected_cost_not_cleared_after_full_invoicing' => $this->expectedCostNotClearedAfterFullInvoicing(),
            'expected_clearing_greater_than_expected_posting' => $this->expectedClearingGreaterThanExpectedPosting(),
            'actual_cost_posted_more_than_once' => $this->actualCostPostedMoreThanOnce(),
            'transfer_shipment_value_not_received' => $this->transferShipmentValueNotReceived(),
            'in_transit_quantity_value_mismatches' => $this->inTransitQuantityValueMismatches(),
            'unapplied_cost_adjustments' => $this->unappliedCostAdjustments(),
            'revaluation_batch_missing_inventory_adjustment' => $this->revaluationBatchMissingInventoryAdjustment(),
            'cost_adjustment_allocation_mismatches' => $this->costAdjustmentAllocationMismatches(),
            'duplicate_adjustment_value_entries' => $this->duplicateAdjustmentValueEntries(),
            'unposted_adjustment_value_entries' => $this->unpostedAdjustmentValueEntries(),
            'value_entry_gl_posting_mismatches' => $this->valueEntryGlPostingMismatches(),
            'entries_modified_in_closed_costing_periods' => $this->entriesModifiedInClosedCostingPeriods(),
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Costing Reconciliation');
        $this->line('Mode: report-only. No item applications, value entries, or G/L entries were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        foreach ($report as $section => $findings) {
            $this->section(str_replace('_', ' ', ucfirst($section)), $findings, (bool) $this->option('details'));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function outboundWithoutApplications(): array
    {
        return ItemLedgerEntry::query()
            ->with('item')
            ->where('quantity', '<', 0)
            ->whereDoesntHave('outboundApplications', fn ($query) => $query->where('is_reversed', false))
            ->limit(200)
            ->get()
            ->map(fn (ItemLedgerEntry $entry): array => $this->finding('outbound_without_application', 'critical', [
                'entry_number' => $entry->entry_number,
                'item_code' => $entry->item?->item_code,
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'quantity' => (float) $entry->quantity,
                'suggested_remediation' => 'Review the source posting and create item applications through the approved costing adjustment process.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function overAppliedItemLedgerEntries(): array
    {
        return ItemLedgerEntry::query()
            ->withSum(['inboundApplications as applied_quantity_sum' => fn ($query) => $query->where('is_reversed', false)], 'applied_quantity')
            ->where('quantity', '>', 0)
            ->get()
            ->filter(fn (ItemLedgerEntry $entry): bool => (float) $entry->applied_quantity_sum > abs((float) $entry->quantity) + 0.0001)
            ->map(fn (ItemLedgerEntry $entry): array => $this->finding('over_applied_item_ledger_entry', 'critical', [
                'entry_number' => $entry->entry_number,
                'quantity' => (float) $entry->quantity,
                'applied_quantity' => (float) $entry->applied_quantity_sum,
                'suggested_remediation' => 'Investigate duplicate application rows and reverse the excess through a controlled repair command after approval.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function applicationQuantityMismatches(): array
    {
        return ItemLedgerEntry::query()
            ->withSum(['outboundApplications as applied_quantity_sum' => fn ($query) => $query->where('is_reversed', false)], 'applied_quantity')
            ->where('quantity', '<', 0)
            ->get()
            ->filter(fn (ItemLedgerEntry $entry): bool => abs(abs((float) $entry->quantity) - (float) $entry->applied_quantity_sum) > 0.0001)
            ->map(fn (ItemLedgerEntry $entry): array => $this->finding('application_quantity_mismatch', 'critical', [
                'entry_number' => $entry->entry_number,
                'quantity' => (float) $entry->quantity,
                'applied_quantity' => (float) $entry->applied_quantity_sum,
                'suggested_remediation' => 'Re-run or inspect the item application service for this outbound entry before closing the costing period.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inboundRemainingQuantityMismatches(): array
    {
        return ItemLedgerEntry::query()
            ->withSum(['inboundApplications as applied_quantity_sum' => fn ($query) => $query->where('is_reversed', false)], 'applied_quantity')
            ->where('quantity', '>', 0)
            ->get()
            ->filter(fn (ItemLedgerEntry $entry): bool => abs(max(0.0, (float) $entry->quantity - (float) $entry->applied_quantity_sum) - (float) $entry->remaining_quantity) > 0.0001)
            ->map(fn (ItemLedgerEntry $entry): array => $this->finding('inbound_remaining_quantity_mismatch', 'warning', [
                'entry_number' => $entry->entry_number,
                'quantity' => (float) $entry->quantity,
                'remaining_quantity' => (float) $entry->remaining_quantity,
                'applied_quantity' => (float) $entry->applied_quantity_sum,
                'suggested_remediation' => 'Compare inbound remaining quantity with item application rows and repair through a reviewed data cleanup.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function valueEntryCostMismatches(): array
    {
        return ItemApplicationEntry::query()
            ->where('is_reversed', false)
            ->with('outboundItemLedgerEntry')
            ->get()
            ->groupBy('outbound_item_ledger_entry_id')
            ->map(function ($applications): ?array {
                $outbound = $applications->first()->outboundItemLedgerEntry;
                $valueCost = abs((float) ValueEntry::query()
                    ->where('item_ledger_entry_no', $outbound?->entry_number)
                    ->where('value_entry_state', 'actual')
                    ->sum('cost_amount_actual'));
                $applicationCost = abs((float) $applications->sum('cost_amount'));

                if (abs($valueCost - $applicationCost) <= 0.0001) {
                    return null;
                }

                return $this->finding('value_entry_cost_mismatch', 'warning', [
                    'entry_number' => $outbound?->entry_number,
                    'application_cost' => $applicationCost,
                    'value_entry_cost' => $valueCost,
                    'suggested_remediation' => 'Run cost adjustment in dry-run mode and inspect source value entries before posting corrections.',
                ]);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expectedCostNotClearedAfterFullInvoicing(): array
    {
        return ValueEntry::query()
            ->where('expected_cost', true)
            ->where('value_entry_state', 'expected')
            ->where('gl_posted', true)
            ->where('completely_invoiced', true)
            ->whereDoesntHave('adjustmentEntries', fn ($query) => $query->where('value_entry_state', 'clearing'))
            ->get()
            ->map(fn (ValueEntry $entry): array => $this->finding('expected_cost_not_cleared_after_full_invoicing', 'critical', [
                'value_entry_no' => $entry->entry_no,
                'document_no' => $entry->document_no,
                'expected_amount' => (float) $entry->cost_amount_expected,
                'suggested_remediation' => 'Post the missing expected-cost clearing entry through the controlled expected-cost service.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expectedClearingGreaterThanExpectedPosting(): array
    {
        return ValueEntry::query()
            ->where('value_entry_state', 'expected')
            ->get()
            ->filter(function (ValueEntry $entry): bool {
                $cleared = abs((float) ValueEntry::query()
                    ->where('reversal_of_value_entry_id', $entry->id)
                    ->where('value_entry_state', 'clearing')
                    ->sum('cost_amount_expected'));

                return $cleared > abs((float) $entry->cost_amount_expected) + 0.0001;
            })
            ->map(fn (ValueEntry $entry): array => $this->finding('expected_clearing_greater_than_expected_posting', 'critical', [
                'value_entry_no' => $entry->entry_no,
                'expected_amount' => (float) $entry->cost_amount_expected,
                'suggested_remediation' => 'Investigate duplicate expected-cost clearing attempts before posting further invoices.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function actualCostPostedMoreThanOnce(): array
    {
        return ValueEntry::query()
            ->selectRaw('item_ledger_entry_no, document_no, document_line_no, count(*) as entry_count')
            ->where('value_entry_state', 'actual')
            ->groupBy('item_ledger_entry_no', 'document_no', 'document_line_no')
            ->havingRaw('count(*) > 1')
            ->get()
            ->map(fn ($row): array => $this->finding('actual_cost_posted_more_than_once', 'critical', [
                'item_ledger_entry_no' => $row->item_ledger_entry_no,
                'document_no' => $row->document_no,
                'document_line_no' => $row->document_line_no,
                'entry_count' => (int) $row->entry_count,
                'suggested_remediation' => 'Review idempotency keys for the source posting and reverse duplicate actual value entries only after approval.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function transferShipmentValueNotReceived(): array
    {
        return ItemLedgerEntry::query()
            ->selectRaw('document_number, sum(quantity) as net_quantity')
            ->where('document_type', 'WAREHOUSE_TRANSFER')
            ->groupBy('document_number')
            ->havingRaw('abs(sum(quantity)) > 0.0001')
            ->get()
            ->map(fn ($row): array => $this->finding('transfer_shipment_value_not_received', 'warning', [
                'document_number' => $row->document_number,
                'net_quantity' => (float) $row->net_quantity,
                'suggested_remediation' => 'Confirm whether the transfer is legitimately in transit or missing receipt entries.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inTransitQuantityValueMismatches(): array
    {
        return ValueEntry::query()
            ->selectRaw('document_no, sum(quantity) as net_quantity, sum(cost_amount_actual) as net_value')
            ->where('item_ledger_entry_type', 5)
            ->groupBy('document_no')
            ->havingRaw('abs(sum(quantity)) > 0.0001 or abs(sum(cost_amount_actual)) > 0.0001')
            ->get()
            ->map(fn ($row): array => $this->finding('in_transit_quantity_value_mismatch', 'warning', [
                'document_no' => $row->document_no,
                'net_quantity' => (float) $row->net_quantity,
                'net_value' => (float) $row->net_value,
                'suggested_remediation' => 'Inspect transfer shipment and receipt value entries for missing or mismatched in-transit clearing.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unappliedCostAdjustments(): array
    {
        return CostAdjustmentBatch::query()
            ->where('dry_run', false)
            ->get()
            ->filter(fn (CostAdjustmentBatch $batch): bool => ! ValueEntry::query()
                ->where('source_type', CostAdjustmentBatch::class)
                ->where('source_id', $batch->id)
                ->exists())
            ->map(fn (CostAdjustmentBatch $batch): array => $this->finding('unapplied_cost_adjustment', 'warning', [
                'batch_number' => $batch->batch_number,
                'reason' => $batch->reason,
                'suggested_remediation' => 'Re-run the cost adjustment posting after confirming the batch was not intentionally zero-impact.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function revaluationBatchMissingInventoryAdjustment(): array
    {
        return CostAdjustmentBatch::query()
            ->where('dry_run', false)
            ->get()
            ->filter(function (CostAdjustmentBatch $batch): bool {
                $remainingDelta = (float) data_get($batch->metadata, 'remaining_inventory_delta', 0);
                if (abs($remainingDelta) <= 0.0001) {
                    return false;
                }

                return ! ValueEntry::query()
                    ->where('source_type', CostAdjustmentBatch::class)
                    ->where('source_id', $batch->id)
                    ->where('document_type', 'INVENTORY_REVALUATION')
                    ->where('value_entry_state', 'adjustment')
                    ->exists();
            })
            ->map(fn (CostAdjustmentBatch $batch): array => $this->finding('revaluation_batch_missing_inventory_adjustment', 'critical', [
                'batch_number' => $batch->batch_number,
                'remaining_inventory_delta' => (float) data_get($batch->metadata, 'remaining_inventory_delta', 0),
                'suggested_remediation' => 'Re-run the approved cost adjustment posting after confirming the remaining layer revaluation Value Entry was not intentionally voided.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function costAdjustmentAllocationMismatches(): array
    {
        return CostAdjustmentBatch::query()
            ->where('dry_run', false)
            ->get()
            ->filter(function (CostAdjustmentBatch $batch): bool {
                $delta = (float) data_get($batch->metadata, 'delta', 0);
                if (abs($delta) <= 0.0001) {
                    return false;
                }

                $valueEntryTotal = (float) ValueEntry::query()
                    ->where('source_type', CostAdjustmentBatch::class)
                    ->where('source_id', $batch->id)
                    ->where('value_entry_state', 'adjustment')
                    ->sum('cost_amount_actual');

                return abs($delta - $valueEntryTotal) > 0.0001;
            })
            ->map(fn (CostAdjustmentBatch $batch): array => $this->finding('cost_adjustment_allocation_mismatch', 'critical', [
                'batch_number' => $batch->batch_number,
                'expected_delta' => (float) data_get($batch->metadata, 'delta', 0),
                'posted_adjustment_total' => (float) ValueEntry::query()
                    ->where('source_type', CostAdjustmentBatch::class)
                    ->where('source_id', $batch->id)
                    ->where('value_entry_state', 'adjustment')
                    ->sum('cost_amount_actual'),
                'suggested_remediation' => 'Review consumed and remaining inventory adjustment Value Entries for this batch before any further layer revaluation.',
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function duplicateAdjustmentValueEntries(): array
    {
        return ValueEntry::query()
            ->selectRaw('source_type, source_id, document_type, original_entry_no, source_line_no, cost_amount_actual, count(*) as entry_count')
            ->where('source_type', CostAdjustmentBatch::class)
            ->where('value_entry_state', 'adjustment')
            ->groupByRaw('source_type, source_id, document_type, original_entry_no, source_line_no, cost_amount_actual')
            ->havingRaw('count(*) > 1')
            ->get()
            ->map(fn ($row): array => $this->finding('duplicate_adjustment_value_entry', 'critical', [
                'source_id' => (int) $row->source_id,
                'document_type' => $row->document_type,
                'original_entry_no' => $row->original_entry_no,
                'source_line_no' => $row->source_line_no,
                'cost_amount_actual' => (float) $row->cost_amount_actual,
                'entry_count' => (int) $row->entry_count,
                'suggested_remediation' => 'Inspect cost adjustment idempotency keys and reverse duplicates only through an approved remediation plan.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function unpostedAdjustmentValueEntries(): array
    {
        return ValueEntry::query()
            ->where('value_entry_state', 'adjustment')
            ->where('gl_posted', false)
            ->get()
            ->map(fn (ValueEntry $entry): array => $this->finding('unposted_adjustment_value_entry', 'critical', [
                'value_entry_no' => $entry->entry_no,
                'document_no' => $entry->document_no,
                'cost_amount_actual' => (float) $entry->cost_amount_actual,
                'suggested_remediation' => 'Post adjustment value entry through ValueEntryAccountingOrchestrator or investigate posting failure.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function valueEntryGlPostingMismatches(): array
    {
        return ValueEntry::query()
            ->where('gl_posted', true)
            ->whereNull('posting_transaction_id')
            ->get()
            ->map(fn (ValueEntry $entry): array => $this->finding('value_entry_gl_posting_mismatch', 'critical', [
                'value_entry_no' => $entry->entry_no,
                'document_no' => $entry->document_no,
                'suggested_remediation' => 'Trace the value entry posting transaction and repair missing posting linkage after review.',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entriesModifiedInClosedCostingPeriods(): array
    {
        $findings = [];

        CostingPeriod::query()
            ->where('is_closed', true)
            ->whereNotNull('closed_at')
            ->get()
            ->each(function (CostingPeriod $period) use (&$findings): void {
                ItemApplicationEntry::query()
                    ->where('created_at', '>', $period->closed_at)
                    ->whereHas('inboundItemLedgerEntry', fn ($query) => $query->whereBetween('posting_date', [$period->start_date, $period->end_date]))
                    ->get()
                    ->each(function (ItemApplicationEntry $entry) use (&$findings, $period): void {
                        $findings[] = $this->finding('entry_modified_in_closed_costing_period', 'critical', [
                            'application_id' => $entry->id,
                            'costing_period_id' => $period->id,
                            'inbound_item_ledger_entry_id' => $entry->inbound_item_ledger_entry_id,
                            'outbound_item_ledger_entry_id' => $entry->outbound_item_ledger_entry_id,
                            'suggested_remediation' => 'Review costing-period lock enforcement and reverse unauthorized application changes.',
                        ]);
                    });
            });

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function finding(string $classification, string $severity, array $data): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            ...$data,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $findings
     */
    private function section(string $title, array $findings, bool $details): void
    {
        $this->line(sprintf('%s: %d', $title, count($findings)));

        if (! $details) {
            return;
        }

        foreach ($findings as $finding) {
            $this->line('  - '.json_encode($finding, JSON_UNESCAPED_SLASHES));
        }
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
}
