<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\JournalBatchStatus;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOperationExecutionStatus;
use App\Models\Manufacturing\ProductionOrder;

class ProductionCompletionReadinessService
{
    /**
     * @return array<int, array{classification: string, severity: string, message: string, context: array<string, mixed>}>
     */
    public function findingsForOrder(ProductionOrder $order): array
    {
        $findings = [];
        $routingLines = $order->routingLines()
            ->with('operationExecutions.journalBatch.lines', 'operationExecutions.qualityHolds', 'operationExecutions.qualityChecks')
            ->orderBy('line_number')
            ->get();

        foreach ($routingLines as $routingLine) {
            $execution = $routingLine->operationExecutions->sortByDesc('id')->first();

            if (! $execution) {
                $findings[] = $this->finding('operation_not_completed', 'critical', 'Routing operation has no execution record.', [
                    'routing_line_id' => $routingLine->id,
                    'operation_no' => $routingLine->operation_no,
                ]);

                continue;
            }

            if (! in_array($execution->status, [ProductionOperationExecutionStatus::Completed, ProductionOperationExecutionStatus::Submitted, ProductionOperationExecutionStatus::Posted], true)) {
                $findings[] = $this->finding('operation_not_completed', 'critical', 'Routing operation execution is not completed.', [
                    'execution_id' => $execution->id,
                    'status' => $execution->status->value,
                ]);
            }

            if ($execution->status === ProductionOperationExecutionStatus::Submitted && ! $execution->journalBatch) {
                $findings[] = $this->finding('journal_batch_missing', 'critical', 'Submitted execution has no production journal batch.', [
                    'execution_id' => $execution->id,
                ]);
            }

            if ($execution->status === ProductionOperationExecutionStatus::Completed && ! $execution->journalBatch) {
                $findings[] = $this->finding('journal_generation_pending', 'warning', 'Completed execution has no generated shop-floor journal batch yet.', [
                    'execution_id' => $execution->id,
                ]);
            }

            if ($execution->journalBatch && $execution->journalBatch->status !== JournalBatchStatus::POSTED) {
                $findings[] = $this->finding('journal_batch_unposted', 'warning', 'Execution journal batch is not posted.', [
                    'execution_id' => $execution->id,
                    'batch_id' => $execution->journalBatch->id,
                    'status' => $execution->journalBatch->status?->value ?? $execution->journalBatch->status,
                ]);
            }

            if ($execution->journalBatch) {
                $lineEntryTypes = $execution->journalBatch->lines
                    ->map(fn ($line): string => $line->entry_type?->value ?? (string) $line->entry_type)
                    ->all();

                if ((int) $execution->setup_seconds + (int) $execution->run_seconds > 0 && ! in_array(ProductionJournalEntryType::Capacity->value, $lineEntryTypes, true)) {
                    $findings[] = $this->finding('capacity_journal_line_missing', 'critical', 'Execution captured time but has no capacity journal line.', [
                        'execution_id' => $execution->id,
                        'batch_id' => $execution->journalBatch->id,
                    ]);
                }

                if ((float) $execution->good_quantity > 0 && ! in_array(ProductionJournalEntryType::Output->value, $lineEntryTypes, true)) {
                    $findings[] = $this->finding('output_journal_line_missing', 'critical', 'Execution captured good output but has no output journal line.', [
                        'execution_id' => $execution->id,
                        'batch_id' => $execution->journalBatch->id,
                    ]);
                }

                if ($order->components()->exists() && ! in_array(ProductionJournalEntryType::Consumption->value, $lineEntryTypes, true)) {
                    $findings[] = $this->finding('consumption_journal_line_missing', 'critical', 'Production order has components but execution journal has no consumption line.', [
                        'execution_id' => $execution->id,
                        'batch_id' => $execution->journalBatch->id,
                    ]);
                }
            }

            if ($execution->status === ProductionOperationExecutionStatus::Submitted && $execution->timeEntries()->exists()) {
                $findings[] = $this->finding('operator_time_submitted_for_review', 'info', 'Submitted execution has captured operator time pending journal posting.', [
                    'execution_id' => $execution->id,
                ]);
            }

            if ($execution->qualityHolds->where('status', 'active')->isNotEmpty()) {
                $findings[] = $this->finding('quality_hold_active', 'critical', 'Execution has an active quality hold.', [
                    'execution_id' => $execution->id,
                ]);
            }

            if ($execution->downtimeEntries()->whereNull('ended_at')->exists()) {
                $findings[] = $this->finding('downtime_open', 'warning', 'Execution has open downtime.', [
                    'execution_id' => $execution->id,
                ]);
            }

            if ($execution->reworkEntries()->whereIn('status', ['identified', 'approved', 'in_progress'])->exists()) {
                $findings[] = $this->finding('rework_open', 'warning', 'Execution has open rework.', [
                    'execution_id' => $execution->id,
                ]);
            }

            if ($execution->qualityChecks->isEmpty()) {
                $findings[] = $this->finding('quality_check_pending', 'info', 'Execution has no quality check recorded.', [
                    'execution_id' => $execution->id,
                ]);
            }
        }

        return $findings;
    }

    /**
     * @return array{classification: string, severity: string, message: string, context: array<string, mixed>}
     */
    private function finding(string $classification, string $severity, string $message, array $context): array
    {
        return compact('classification', 'severity', 'message', 'context');
    }
}
