<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProductionOrderStatus;
use App\Models\CapacityLedgerEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\ProductionDowntimeEntry;
use App\Models\Manufacturing\ProductionOperationExecution;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\ProductionQualityHold;
use App\Models\Manufacturing\ProductionReworkEntry;
use App\Models\Manufacturing\ProductionScrapEntry;
use App\Models\Manufacturing\ProductionTimeEntry;
use App\Models\ProductionJournalLine;
use App\Models\ValueEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

#[Signature('biwms:shop-floor-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path} {--production-order= : Limit to one production order ID or document number} {--work-center= : Limit to one work center ID or code} {--machine-center= : Limit to one machine center ID or code} {--operator= : Limit to one employee ID or employee number} {--from-date= : Limit execution date from YYYY-MM-DD} {--to-date= : Limit execution date to YYYY-MM-DD}')]
#[Description('Report shop-floor execution, time, journal, quality, and production-progress consistency issues without writing data.')]
class BiwmsShopFloorReconcile extends Command
{
    public function handle(): int
    {
        $report = [
            'mode' => 'report-only',
            'filters' => [
                'production_order' => $this->option('production-order'),
                'work_center' => $this->option('work-center'),
                'machine_center' => $this->option('machine-center'),
                'operator' => $this->option('operator'),
                'from_date' => $this->option('from-date'),
                'to_date' => $this->option('to-date'),
            ],
            'findings' => [
                'released_order_without_routing_operations' => $this->releasedOrdersWithoutRouting(),
                'operation_without_execution_status' => $this->operationsWithoutExecution(),
                'operation_started_multiple_times' => $this->duplicateStartEvents(),
                'operation_completed_without_start' => $this->completedWithoutStart(),
                'operation_posted_without_completion' => $this->postedWithoutCompletion(),
                'operation_sequence_violation' => $this->sequenceViolations(),
                'operator_time_overlap' => $this->timeOverlaps('employee_id', 'operator_time_overlap'),
                'machine_time_overlap' => $this->timeOverlaps('machine_center_id', 'machine_time_overlap'),
                'open_execution_exceeds_limit' => $this->openExecutionsExceedingLimit(),
                'setup_time_missing' => $this->completedExecutionsMissingTime('setup_seconds', 'setup_time_missing'),
                'run_time_missing' => $this->completedExecutionsMissingTime('run_seconds', 'run_time_missing'),
                'journal_batch_missing' => $this->submittedExecutionsWithoutJournal(),
                'journal_batch_unposted' => $this->executionsWithUnpostedJournal(),
                'journal_batch_failed' => [],
                'journal_line_without_execution' => $this->journalLinesWithoutExecution(),
                'execution_without_journal_line' => $this->executionWithoutJournalLine(),
                'posted_journal_line_missing_ledger_entry' => $this->postedJournalLinesMissingLedgerEntry(),
                'scrap_without_reason' => $this->scrapWithoutReason(),
                'scrap_approval_missing' => $this->scrapApprovalMissing(),
                'rework_open' => $this->openRework(),
                'downtime_open' => $this->openDowntime(),
                'downtime_duration_invalid' => $this->invalidDowntimeDuration(),
                'quality_check_missing' => $this->completedExecutionsWithoutQualityCheck(),
                'quality_hold_active_on_finished_order' => $this->qualityHoldActiveOnFinishedOrder(),
                'quality_hold_release_broken' => $this->brokenQualityHoldRelease(),
                'posted_execution_reversal_missing' => $this->postedExecutionReversalMissing(),
                'reversal_chain_broken' => $this->reversalChainBroken(),
                'production_progress_mismatch' => [],
                'finished_order_with_open_execution' => $this->finishedOrderWithOpenExecution(),
                'finished_order_with_unposted_journal' => $this->finishedOrderWithUnpostedJournal(),
                'capacity_value_entry_missing' => $this->capacityValueEntryMissing(),
                'manufacturing_gl_missing' => $this->manufacturingGlMissing(),
                'manufacturing_gl_without_value_entry' => $this->manufacturingGlWithoutValueEntry(),
            ],
        ];

        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, (string) $exportPath);
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Shop Floor Reconciliation');
        $this->line('Mode: report-only. No shop-floor, ledger, journal, or production records were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        foreach ($report['findings'] as $classification => $findings) {
            $count = count($findings);
            $this->line(str($classification)->replace('_', ' ')->headline().": {$count}");

            if ($this->option('details')) {
                foreach ($findings as $finding) {
                    $this->line('  - '.$finding['message'].' '.json_encode($finding['context'], JSON_UNESCAPED_SLASHES));
                }
            }
        }

        return self::SUCCESS;
    }

    private function releasedOrdersWithoutRouting(): array
    {
        return ProductionOrder::query()
            ->where('status', ProductionOrderStatus::RELEASED)
            ->whereDoesntHave('routingLines')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrder $order): array => $this->finding('released_order_without_routing_operations', 'warning', 'Released order has no routing operations.', [
                'production_order_id' => $order->id,
                'production_order_no' => $order->document_number,
            ]))
            ->all();
    }

    private function operationsWithoutExecution(): array
    {
        return $this->routingLineQuery()
            ->whereDoesntHave('operationExecutions')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOrderRoutingLine $line): array => $this->finding('operation_without_execution_status', 'info', 'Routing operation has no execution yet.', [
                'routing_line_id' => $line->id,
                'production_order_id' => $line->production_order_id,
                'operation_no' => $line->operation_no,
            ]))
            ->all();
    }

    private function duplicateStartEvents(): array
    {
        return $this->executionQuery()
            ->whereHas('events', fn (Builder $query): Builder => $query->whereIn('event_type', ['setup_started', 'run_started']))
            ->withCount(['events as start_event_count' => fn (Builder $query): Builder => $query->whereIn('event_type', ['setup_started', 'run_started'])])
            ->limit(250)
            ->get()
            ->filter(fn (ProductionOperationExecution $execution): bool => (int) $execution->start_event_count > 2)
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('operation_started_multiple_times', 'warning', 'Execution has repeated start events.', [
                'execution_id' => $execution->id,
                'start_event_count' => $execution->start_event_count,
            ]))
            ->values()
            ->all();
    }

    private function completedWithoutStart(): array
    {
        return $this->executionQuery()
            ->whereIn('status', ['completed', 'submitted', 'posted'])
            ->whereDoesntHave('events', fn (Builder $query): Builder => $query->whereIn('event_type', ['setup_started', 'run_started']))
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('operation_completed_without_start', 'critical', 'Execution completed without a start event.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function postedWithoutCompletion(): array
    {
        return $this->executionQuery()
            ->where('status', 'posted')
            ->where('posted_at', null)
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('operation_posted_without_completion', 'critical', 'Execution is posted without posted timestamp.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function sequenceViolations(): array
    {
        return $this->executionQuery()
            ->whereIn('status', ['completed', 'submitted', 'posted'])
            ->with('routingLine')
            ->limit(500)
            ->get()
            ->filter(function (ProductionOperationExecution $execution): bool {
                $previous = ProductionOrderRoutingLine::query()
                    ->where('production_order_id', $execution->production_order_id)
                    ->where('line_number', '<', $execution->routingLine?->line_number)
                    ->orderByDesc('line_number')
                    ->first();

                return $previous !== null && ! ProductionOperationExecution::query()
                    ->where('routing_line_id', $previous->id)
                    ->whereIn('status', ['completed', 'submitted', 'posted'])
                    ->exists();
            })
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('operation_sequence_violation', 'critical', 'Operation completed before previous routing operation.', [
                'execution_id' => $execution->id,
                'routing_line_id' => $execution->routing_line_id,
            ]))
            ->values()
            ->all();
    }

    private function timeOverlaps(string $column, string $classification): array
    {
        $entries = ProductionTimeEntry::query()
            ->whereNotNull($column)
            ->whereNotNull('started_at')
            ->whereNull('ended_at')
            ->select($column)
            ->selectRaw('count(*) as open_count')
            ->groupBy($column)
            ->havingRaw('count(*) > 1')
            ->limit(250)
            ->get();

        return $entries
            ->map(fn ($entry): array => $this->finding($classification, 'critical', 'More than one open production timer exists for the same resource.', [
                $column => $entry->{$column},
                'open_count' => $entry->open_count,
            ]))
            ->all();
    }

    private function openExecutionsExceedingLimit(): array
    {
        return $this->executionQuery()
            ->whereIn('status', ['setup_started', 'running', 'paused', 'setup_paused'])
            ->where('updated_at', '<', now()->subHours(16))
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('open_execution_exceeds_limit', 'warning', 'Execution has been open longer than the Phase 1E safety threshold.', [
                'execution_id' => $execution->id,
                'updated_at' => $execution->updated_at?->toDateTimeString(),
            ]))
            ->all();
    }

    private function completedExecutionsMissingTime(string $column, string $classification): array
    {
        return $this->executionQuery()
            ->whereIn('status', ['completed', 'submitted', 'posted'])
            ->where($column, 0)
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding($classification, 'warning', 'Completed execution is missing expected time capture.', [
                'execution_id' => $execution->id,
                'field' => $column,
            ]))
            ->all();
    }

    private function submittedExecutionsWithoutJournal(): array
    {
        return $this->executionQuery()
            ->whereIn('status', ['submitted', 'posted'])
            ->whereNull('production_journal_batch_id')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('journal_batch_missing', 'critical', 'Submitted or posted execution has no journal batch.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function executionsWithUnpostedJournal(): array
    {
        return $this->executionQuery()
            ->whereHas('journalBatch', fn (Builder $query): Builder => $query->whereNot('status', 'posted'))
            ->with('journalBatch')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('journal_batch_unposted', 'warning', 'Execution journal batch is not posted.', [
                'execution_id' => $execution->id,
                'batch_id' => $execution->production_journal_batch_id,
                'batch_status' => $execution->journalBatch?->status?->value ?? $execution->journalBatch?->status,
            ]))
            ->all();
    }

    private function journalLinesWithoutExecution(): array
    {
        return ProductionJournalLine::query()
            ->whereNotNull('shop_floor_idempotency_key')
            ->whereNull('production_operation_execution_id')
            ->limit(250)
            ->get()
            ->map(fn ($line): array => $this->finding('journal_line_without_execution', 'critical', 'Shop-floor journal line is missing execution reference.', [
                'journal_line_id' => $line->id,
            ]))
            ->all();
    }

    private function postedJournalLinesMissingLedgerEntry(): array
    {
        return ProductionJournalLine::query()
            ->whereNotNull('shop_floor_idempotency_key')
            ->where('line_status', 'posted')
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $nested): void {
                        $nested->whereIn('entry_type', ['consumption', 'output', 'scrap'])
                            ->whereNull('item_ledger_entry_id');
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('entry_type', 'capacity')
                            ->whereNull('capacity_ledger_entry_id');
                    });
            })
            ->limit(250)
            ->get()
            ->map(fn (ProductionJournalLine $line): array => $this->finding('posted_journal_line_missing_ledger_entry', 'critical', 'Posted shop-floor journal line is missing its ledger-entry link.', [
                'journal_line_id' => $line->id,
                'entry_type' => $line->entry_type?->value ?? $line->entry_type,
            ]))
            ->all();
    }

    private function executionWithoutJournalLine(): array
    {
        return $this->executionQuery()
            ->whereNotNull('production_journal_batch_id')
            ->whereDoesntHave('journalLines')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('execution_without_journal_line', 'critical', 'Execution has a journal batch but no journal lines.', [
                'execution_id' => $execution->id,
                'batch_id' => $execution->production_journal_batch_id,
            ]))
            ->all();
    }

    private function scrapWithoutReason(): array
    {
        return ProductionScrapEntry::query()
            ->whereNull('production_scrap_reason_id')
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => $this->finding('scrap_without_reason', 'critical', 'Scrap entry is missing a reason.', [
                'scrap_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function scrapApprovalMissing(): array
    {
        return ProductionScrapEntry::query()
            ->where('requires_approval', true)
            ->whereNull('approved_at')
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => $this->finding('scrap_approval_missing', 'warning', 'Scrap entry requires approval.', [
                'scrap_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function openRework(): array
    {
        return ProductionReworkEntry::query()
            ->whereIn('status', ['identified', 'approved', 'in_progress'])
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => $this->finding('rework_open', 'warning', 'Rework entry is still open.', [
                'rework_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function openDowntime(): array
    {
        return ProductionDowntimeEntry::query()
            ->whereNull('ended_at')
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => $this->finding('downtime_open', 'warning', 'Downtime entry is still open.', [
                'downtime_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function invalidDowntimeDuration(): array
    {
        return ProductionDowntimeEntry::query()
            ->whereNotNull('ended_at')
            ->where('duration_seconds', '<=', 0)
            ->limit(250)
            ->get()
            ->map(fn ($entry): array => $this->finding('downtime_duration_invalid', 'warning', 'Closed downtime entry has invalid duration.', [
                'downtime_entry_id' => $entry->id,
            ]))
            ->all();
    }

    private function completedExecutionsWithoutQualityCheck(): array
    {
        return $this->executionQuery()
            ->whereIn('status', ['completed', 'submitted', 'posted'])
            ->whereDoesntHave('qualityChecks')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('quality_check_missing', 'info', 'Completed execution has no quality check.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function qualityHoldActiveOnFinishedOrder(): array
    {
        return $this->executionQuery()
            ->whereHas('productionOrder', fn (Builder $query): Builder => $query->where('status', ProductionOrderStatus::FINISHED))
            ->whereHas('qualityHolds', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('quality_hold_active_on_finished_order', 'critical', 'Finished production order has active quality hold.', [
                'execution_id' => $execution->id,
                'production_order_id' => $execution->production_order_id,
            ]))
            ->all();
    }

    private function brokenQualityHoldRelease(): array
    {
        return ProductionQualityHold::query()
            ->where('status', 'released')
            ->whereNull('released_at')
            ->limit(250)
            ->get()
            ->map(fn ($hold): array => $this->finding('quality_hold_release_broken', 'critical', 'Released quality hold is missing release timestamp.', [
                'quality_hold_id' => $hold->id,
            ]))
            ->all();
    }

    private function postedExecutionReversalMissing(): array
    {
        return $this->executionQuery()
            ->where('status', 'reversed')
            ->whereNull('reversal_execution_id')
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('posted_execution_reversal_missing', 'critical', 'Reversed execution is missing reversal chain reference.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function reversalChainBroken(): array
    {
        return $this->executionQuery()
            ->whereNotNull('reversal_execution_id')
            ->whereDoesntHave('events', fn (Builder $query): Builder => $query->where('event_type', 'reversed'))
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('reversal_chain_broken', 'critical', 'Execution has reversal reference without reversal event.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function finishedOrderWithOpenExecution(): array
    {
        return $this->executionQuery()
            ->whereHas('productionOrder', fn (Builder $query): Builder => $query->where('status', ProductionOrderStatus::FINISHED))
            ->whereNotIn('status', ['posted', 'cancelled', 'reversed'])
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('finished_order_with_open_execution', 'critical', 'Finished order has open execution.', [
                'execution_id' => $execution->id,
            ]))
            ->all();
    }

    private function finishedOrderWithUnpostedJournal(): array
    {
        return $this->executionQuery()
            ->whereHas('productionOrder', fn (Builder $query): Builder => $query->where('status', ProductionOrderStatus::FINISHED))
            ->whereHas('journalBatch', fn (Builder $query): Builder => $query->whereNot('status', 'posted'))
            ->limit(250)
            ->get()
            ->map(fn (ProductionOperationExecution $execution): array => $this->finding('finished_order_with_unposted_journal', 'critical', 'Finished order has unposted shop-floor journal.', [
                'execution_id' => $execution->id,
                'batch_id' => $execution->production_journal_batch_id,
            ]))
            ->all();
    }

    private function capacityValueEntryMissing(): array
    {
        return CapacityLedgerEntry::query()
            ->whereNotNull('idempotency_key')
            ->limit(250)
            ->get()
            ->filter(fn (CapacityLedgerEntry $entry): bool => ! ValueEntry::query()
                ->where('source_type', CapacityLedgerEntry::class)
                ->where('source_id', $entry->id)
                ->exists())
            ->map(fn (CapacityLedgerEntry $entry): array => $this->finding('capacity_value_entry_missing', 'critical', 'Capacity ledger entry has no matching value entry.', [
                'capacity_ledger_entry_id' => $entry->id,
                'production_order_id' => $entry->production_order_id,
            ]))
            ->values()
            ->all();
    }

    private function manufacturingGlMissing(): array
    {
        return ValueEntry::query()
            ->whereIn('source_module', ['production', 'manufacturing'])
            ->where('gl_posted', false)
            ->limit(250)
            ->get()
            ->map(fn (ValueEntry $entry): array => $this->finding('manufacturing_gl_missing', 'critical', 'Manufacturing value entry is not posted to G/L.', [
                'value_entry_id' => $entry->id,
                'document_no' => $entry->document_no,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
            ]))
            ->all();
    }

    private function manufacturingGlWithoutValueEntry(): array
    {
        return ProductionJournalLine::query()
            ->whereNotNull('shop_floor_idempotency_key')
            ->where('line_status', 'posted')
            ->where(function (Builder $query): void {
                $query->whereNotNull('item_ledger_entry_id')
                    ->orWhereNotNull('capacity_ledger_entry_id');
            })
            ->limit(250)
            ->get()
            ->filter(function (ProductionJournalLine $line): bool {
                if ($line->item_ledger_entry_id) {
                    $itemLedgerEntry = ItemLedgerEntry::query()->find($line->item_ledger_entry_id);

                    if (! $itemLedgerEntry) {
                        return true;
                    }

                    return ! ValueEntry::query()
                        ->where('item_ledger_entry_no', $itemLedgerEntry?->entry_number)
                        ->exists();
                }

                return ! ValueEntry::query()
                    ->where('source_type', CapacityLedgerEntry::class)
                    ->where('source_id', $line->capacity_ledger_entry_id)
                    ->exists();
            })
            ->map(fn (ProductionJournalLine $line): array => $this->finding('manufacturing_gl_without_value_entry', 'critical', 'Posted shop-floor journal line has a ledger entry without a value entry.', [
                'journal_line_id' => $line->id,
                'item_ledger_entry_id' => $line->item_ledger_entry_id,
                'capacity_ledger_entry_id' => $line->capacity_ledger_entry_id,
            ]))
            ->values()
            ->all();
    }

    private function routingLineQuery(): Builder
    {
        return ProductionOrderRoutingLine::query()
            ->whereHas('productionOrder', fn (Builder $query): Builder => $this->applyProductionOrderFilter($query));
    }

    private function executionQuery(): Builder
    {
        return ProductionOperationExecution::query()
            ->whereHas('productionOrder', fn (Builder $query): Builder => $this->applyProductionOrderFilter($query))
            ->when($this->option('work-center'), fn (Builder $query, string $value): Builder => $query->whereHas('workCenter', fn (Builder $workCenterQuery): Builder => $this->idOrCode($workCenterQuery, $value, 'code')))
            ->when($this->option('machine-center'), fn (Builder $query, string $value): Builder => $query->whereHas('machineCenter', fn (Builder $machineQuery): Builder => $this->idOrCode($machineQuery, $value, 'code')))
            ->when($this->option('operator'), fn (Builder $query, string $value): Builder => $query->whereHas('operatorEmployee', fn (Builder $employeeQuery): Builder => $this->idOrCode($employeeQuery, $value, 'employee_number')))
            ->when($this->option('from-date'), fn (Builder $query, string $value): Builder => $query->whereDate('execution_date', '>=', $value))
            ->when($this->option('to-date'), fn (Builder $query, string $value): Builder => $query->whereDate('execution_date', '<=', $value));
    }

    private function applyProductionOrderFilter(Builder $query): Builder
    {
        $filter = $this->option('production-order');

        return $query->when($filter, fn (Builder $productionOrderQuery, string $value): Builder => $this->idOrCode($productionOrderQuery, $value, 'document_number'));
    }

    private function idOrCode(Builder $query, string $value, string $codeColumn): Builder
    {
        return $query->where(function (Builder $nested) use ($value, $codeColumn): void {
            if (is_numeric($value)) {
                $nested->where('id', (int) $value)->orWhere($codeColumn, $value);

                return;
            }

            $nested->where($codeColumn, $value);
        });
    }

    private function finding(string $classification, string $severity, string $message, array $context): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'suggested_remediation' => $this->remediation($classification),
        ];
    }

    private function remediation(string $classification): string
    {
        return match ($classification) {
            'journal_batch_missing', 'execution_without_journal_line' => 'Create or regenerate the production journal from the execution, then submit through the normal journal workflow.',
            'operator_time_overlap', 'machine_time_overlap' => 'Review open timers and close or reverse the incorrect operational timer entry.',
            'quality_hold_active_on_finished_order', 'quality_hold_release_broken' => 'Review quality hold history and release with authorization or reopen the production process.',
            'operation_sequence_violation' => 'Review routing sequence override authorization and operation history.',
            default => 'Review the source document and correct through an authorized append-only correction or reversal.',
        };
    }

    private function exportReport(array $report, string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
