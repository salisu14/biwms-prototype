<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\JournalBatchStatus;
use App\Enums\JournalLineStatus;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOperationExecutionStatus;
use App\Enums\ProductionOperatorAssignmentStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionQualityDisposition;
use App\Enums\ProductionQualityInspectionStage;
use App\Enums\ProductionQualityResult;
use App\Enums\ProductionScrapPostingTreatment;
use App\Enums\ProductionScrapStage;
use App\Models\Employee;
use App\Models\Manufacturing\ProductionDowntimeEntry;
use App\Models\Manufacturing\ProductionDowntimeReason;
use App\Models\Manufacturing\ProductionOperationExecution;
use App\Models\Manufacturing\ProductionOperationExecutionEvent;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\ProductionQualityCheck;
use App\Models\Manufacturing\ProductionQualityHold;
use App\Models\Manufacturing\ProductionScrapEntry;
use App\Models\Manufacturing\ProductionScrapReason;
use App\Models\Manufacturing\ProductionTimeEntry;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\ProductionJournalTemplate;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Services\Posting\ProductionJournalPostingRoutine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductionOperationExecutionService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
        private readonly ProductionJournalPostingRoutine $postingRoutine,
        private readonly ProductionOperationDependencyReadinessService $dependencyReadinessService,
        private readonly ProductionOperationDependencyProgressService $dependencyProgressService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function getOrCreateExecution(ProductionOrder $order, ProductionOrderRoutingLine $routingLine, ?Employee $operator = null, array $attributes = []): ProductionOperationExecution
    {
        return DB::transaction(function () use ($order, $routingLine, $operator, $attributes): ProductionOperationExecution {
            $lockedOrder = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);
            $lockedRoutingLine = ProductionOrderRoutingLine::query()->lockForUpdate()->findOrFail($routingLine->id);

            if ($lockedOrder->status !== ProductionOrderStatus::RELEASED) {
                throw new RuntimeException('Only released production orders can be executed on the shop floor.');
            }

            $idempotencyKey = $attributes['idempotency_key'] ?? $this->idempotencyKey('execution', [
                $lockedOrder->id,
                $lockedRoutingLine->id,
                $operator?->id ?? 'unassigned',
            ]);

            $existing = ProductionOperationExecution::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $execution = ProductionOperationExecution::query()->create([
                'business_id' => $attributes['business_id'] ?? null,
                'production_order_id' => $lockedOrder->id,
                'routing_line_id' => $lockedRoutingLine->id,
                'operation_no' => $lockedRoutingLine->operation_no,
                'work_center_id' => $lockedRoutingLine->work_center_id,
                'machine_center_id' => $attributes['machine_center_id'] ?? $lockedRoutingLine->machine_center_id,
                'operator_employee_id' => $operator?->id,
                'operator_user_id' => $operator?->user?->id,
                'shift_id' => $attributes['shift_id'] ?? null,
                'location_id' => $attributes['location_id'] ?? null,
                'status' => ProductionOperationExecutionStatus::Ready,
                'planned_quantity' => $attributes['planned_quantity'] ?? $lockedRoutingLine->expected_output_quantity ?? $lockedOrder->quantity_base ?? 0,
                'execution_date' => $attributes['execution_date'] ?? now()->toDateString(),
                'posting_date' => $attributes['posting_date'] ?? now()->toDateString(),
                'source_device' => $attributes['source_device'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $attributes['created_by'] ?? auth()->id(),
            ]);

            if ($operator) {
                $this->assignOperator($execution, $operator, $attributes['created_by'] ?? auth()->id());
            }

            $this->recordEvent($execution, 'created', null, ProductionOperationExecutionStatus::Ready, $attributes['created_by'] ?? auth()->id(), $operator?->id);

            return $execution->fresh();
        });
    }

    public function assignOperator(ProductionOperationExecution $execution, Employee $employee, ?int $userId = null): void
    {
        $execution->assignments()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'user_id' => $employee->user?->id,
                'status' => ProductionOperatorAssignmentStatus::Assigned,
                'assigned_at' => now(),
                'assigned_by' => $userId,
            ],
        );

        $this->audit($execution, 'operator_assigned', $userId, ['employee_id' => $employee->id]);
    }

    public function startSetup(ProductionOperationExecution $execution, ?int $userId = null, ?string $idempotencyKey = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'start', $userId);

        return $this->transitionWithTimer($execution, ProductionOperationExecutionStatus::SetupStarted, 'setup', $userId, $idempotencyKey);
    }

    public function pauseSetup(ProductionOperationExecution $execution, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'pause', $userId);

        return $this->stopTimerAndTransition($execution, ProductionOperationExecutionStatus::SetupPaused, 'setup', $userId);
    }

    public function resumeSetup(ProductionOperationExecution $execution, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'resume', $userId);

        return $this->transitionWithTimer($execution, ProductionOperationExecutionStatus::SetupStarted, 'setup', $userId);
    }

    public function completeSetup(ProductionOperationExecution $execution, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'complete', $userId);

        return $this->stopTimerAndTransition($execution, ProductionOperationExecutionStatus::SetupCompleted, 'setup', $userId);
    }

    public function startRun(ProductionOperationExecution $execution, ?int $userId = null, ?string $idempotencyKey = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'start', $userId);

        return $this->transitionWithTimer($execution, ProductionOperationExecutionStatus::Running, 'run', $userId, $idempotencyKey);
    }

    public function pauseRun(ProductionOperationExecution $execution, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'pause', $userId);

        return $this->stopTimerAndTransition($execution, ProductionOperationExecutionStatus::Paused, 'run', $userId);
    }

    public function resumeRun(ProductionOperationExecution $execution, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'resume', $userId);

        return $this->transitionWithTimer($execution, ProductionOperationExecutionStatus::Running, 'run', $userId);
    }

    /**
     * @param  array{good_quantity?: float|int|string, scrap_quantity?: float|int|string, rework_quantity?: float|int|string}  $quantities
     */
    public function completeRun(ProductionOperationExecution $execution, array $quantities = [], ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'complete', $userId);

        return DB::transaction(function () use ($execution, $quantities, $userId): ProductionOperationExecution {
            $locked = $this->lockExecution($execution);
            $this->assertTransition($locked, ProductionOperationExecutionStatus::Completed);
            $this->ensureNoActiveQualityHold($locked);
            $this->enforcePreviousOperationComplete($locked);
            $this->closeOpenTimer($locked, 'run', $userId);

            $locked->update([
                'status' => ProductionOperationExecutionStatus::Completed,
                'good_quantity' => $quantities['good_quantity'] ?? $locked->good_quantity,
                'scrap_quantity' => $quantities['scrap_quantity'] ?? $locked->scrap_quantity,
                'rework_quantity' => $quantities['rework_quantity'] ?? $locked->rework_quantity,
            ]);

            $this->recordEvent($locked, 'completed', $locked->status, ProductionOperationExecutionStatus::Completed, $userId, $locked->operator_employee_id, $quantities);
            $this->dependencyProgressService->syncForProductionOrder($locked->productionOrder);

            return $locked->fresh();
        });
    }

    public function addManualTime(ProductionOperationExecution $execution, string $timeType, int $durationSeconds, ?Employee $employee = null, ?int $userId = null, ?string $reason = null): ProductionTimeEntry
    {
        $this->authorize($execution, 'correctTime', $userId);

        if ($durationSeconds <= 0) {
            throw new RuntimeException('Manual production time must be greater than zero seconds.');
        }

        return DB::transaction(function () use ($execution, $timeType, $durationSeconds, $employee, $userId, $reason): ProductionTimeEntry {
            $locked = $this->lockExecution($execution);
            $entry = ProductionTimeEntry::query()->create([
                'production_operation_execution_id' => $locked->id,
                'employee_id' => $employee?->id ?? $locked->operator_employee_id,
                'machine_center_id' => $locked->machine_center_id,
                'time_type' => $timeType,
                'duration_seconds' => $durationSeconds,
                'manual' => true,
                'created_by' => $userId,
                'reason' => $reason,
                'idempotency_key' => $this->idempotencyKey('manual-time', [$locked->id, $timeType, $durationSeconds, $employee?->id, $reason]),
            ]);

            $this->applyDuration($locked, $timeType, $durationSeconds, $employee !== null);
            $this->recordEvent($locked, 'manual_time_recorded', $locked->status, $locked->status, $userId, $employee?->id, [
                'time_type' => $timeType,
                'duration_seconds' => $durationSeconds,
            ]);

            return $entry;
        });
    }

    public function submit(ProductionOperationExecution $execution, ?int $userId = null, bool $createJournal = false): ProductionOperationExecution
    {
        $this->authorize($execution, 'submit', $userId);

        return DB::transaction(function () use ($execution, $userId, $createJournal): ProductionOperationExecution {
            $locked = $this->lockExecution($execution);

            if ($locked->status === ProductionOperationExecutionStatus::Submitted) {
                if ($createJournal && ! $locked->journalBatch) {
                    $this->createJournalFromExecution($locked, $userId);
                }

                return $locked->fresh();
            }

            $this->assertTransition($locked, ProductionOperationExecutionStatus::Submitted);

            if ($createJournal) {
                $this->createJournalFromExecution($locked, $userId);
            }

            $locked->update([
                'status' => ProductionOperationExecutionStatus::Submitted,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $this->recordEvent($locked, 'submitted', $locked->status, ProductionOperationExecutionStatus::Submitted, $userId, $locked->operator_employee_id);

            return $locked->fresh();
        });
    }

    public function postJournal(ProductionOperationExecution $execution, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'post', $userId);

        return DB::transaction(function () use ($execution, $userId): ProductionOperationExecution {
            $locked = $this->lockExecution($execution);

            if ($locked->status === ProductionOperationExecutionStatus::Posted && $locked->journalBatch?->status === JournalBatchStatus::POSTED) {
                return $locked->fresh();
            }

            $this->assertTransition($locked, ProductionOperationExecutionStatus::Posted);

            $batch = $locked->journalBatch;
            if (! $batch) {
                $batch = $this->createJournalFromExecution($locked, $userId);
            }

            $batch->update(['status' => JournalBatchStatus::RELEASED]);
            $result = $this->postingRoutine->post($batch->fresh('lines'));

            if (! $result->success) {
                throw new RuntimeException('Production journal posting failed: '.implode('; ', $result->errors));
            }

            $locked->update([
                'status' => ProductionOperationExecutionStatus::Posted,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            $this->recordEvent($locked, 'posted', $locked->status, ProductionOperationExecutionStatus::Posted, $userId, $locked->operator_employee_id);
            $this->auditTrailService->recordPosting($locked, $userId, 'SHOP_FLOOR_EXECUTION', (string) $locked->id);
            $this->dependencyProgressService->syncForProductionOrder($locked->productionOrder);

            return $locked->fresh();
        });
    }

    public function reverse(ProductionOperationExecution $execution, string $reason, ?int $userId = null): ProductionOperationExecution
    {
        $this->authorize($execution, 'reverse', $userId);

        return DB::transaction(function () use ($execution, $reason, $userId): ProductionOperationExecution {
            $locked = $this->lockExecution($execution);
            $this->assertTransition($locked, ProductionOperationExecutionStatus::Reversed);

            $replacement = $locked->replicate([
                'status',
                'submitted_by',
                'submitted_at',
                'approved_by',
                'approved_at',
                'posted_by',
                'posted_at',
                'reversed_by',
                'reversed_at',
                'production_journal_batch_id',
                'idempotency_key',
            ]);
            $replacement->status = ProductionOperationExecutionStatus::Reversed;
            $replacement->reason_code = $locked->reason_code;
            $replacement->notes = $reason;
            $replacement->original_execution_id = $locked->id;
            $replacement->idempotency_key = $this->idempotencyKey('execution-reversal', [$locked->id, $reason]);
            $replacement->save();

            $locked->update([
                'status' => ProductionOperationExecutionStatus::Reversed,
                'reversed_by' => $userId,
                'reversed_at' => now(),
                'reversal_execution_id' => $replacement->id,
            ]);

            $this->recordEvent($locked, 'reversed', $locked->status, ProductionOperationExecutionStatus::Reversed, $userId, $locked->operator_employee_id, ['reason' => $reason]);
            $this->auditTrailService->recordReversal($locked, $userId, 'SHOP_FLOOR_EXECUTION', (string) $locked->id, ['reason' => $reason]);
            $this->dependencyProgressService->syncForProductionOrder($locked->productionOrder);

            return $locked->fresh();
        });
    }

    public function recordScrap(ProductionOperationExecution $execution, ProductionScrapReason $reason, float $quantity, ?int $userId = null, ?string $idempotencyKey = null): ProductionScrapEntry
    {
        $this->authorize($execution, 'recordScrap', $userId);

        if ($quantity <= 0) {
            throw new RuntimeException('Scrap quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($execution, $reason, $quantity, $userId, $idempotencyKey): ProductionScrapEntry {
            $locked = $this->lockExecution($execution);

            $entry = ProductionScrapEntry::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey ?? $this->idempotencyKey('scrap', [$locked->id, $reason->id, $quantity])],
                [
                    'production_operation_execution_id' => $locked->id,
                    'production_scrap_reason_id' => $reason->id,
                    'stage' => $reason->stage ?? ProductionScrapStage::Process,
                    'posting_treatment' => $reason->default_posting_treatment ?? ProductionScrapPostingTreatment::OperationalOnly,
                    'quantity' => $quantity,
                    'unit_of_measure_code' => $locked->productionOrder?->unit_of_measure_code,
                    'requires_approval' => (bool) $reason->requires_approval,
                ],
            );

            $locked->increment('scrap_quantity', $quantity);
            $this->recordEvent($locked, 'scrap_recorded', $locked->status, $locked->status, $userId, $locked->operator_employee_id, [
                'scrap_entry_id' => $entry->id,
                'quantity' => $quantity,
            ]);

            return $entry;
        });
    }

    public function recordDowntime(ProductionOperationExecution $execution, ?ProductionDowntimeReason $reason, Carbon $startedAt, ?Carbon $endedAt = null, ?int $userId = null): ProductionDowntimeEntry
    {
        $this->authorize($execution, 'recordDowntime', $userId);

        return DB::transaction(function () use ($execution, $reason, $startedAt, $endedAt, $userId): ProductionDowntimeEntry {
            $locked = $this->lockExecution($execution);
            $duration = $endedAt ? max(0, $startedAt->diffInSeconds($endedAt, false)) : 0;

            $entry = ProductionDowntimeEntry::query()->create([
                'production_operation_execution_id' => $locked->id,
                'production_downtime_reason_id' => $reason?->id,
                'category' => $reason?->category ?? 'unplanned',
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $duration,
                'planned' => ($reason?->category?->value ?? $reason?->category) === 'planned',
                'requires_approval' => (bool) $reason?->requires_approval,
                'idempotency_key' => $this->idempotencyKey('downtime', [$locked->id, $startedAt->toIso8601String(), $endedAt?->toIso8601String()]),
            ]);

            if ($duration > 0) {
                $locked->increment('downtime_seconds', $duration);
            }

            $this->recordEvent($locked, 'downtime_recorded', $locked->status, $locked->status, $userId, $locked->operator_employee_id, [
                'downtime_entry_id' => $entry->id,
                'duration_seconds' => $duration,
            ]);

            return $entry;
        });
    }

    public function recordQualityCheck(ProductionOperationExecution $execution, ProductionQualityInspectionStage $stage, ProductionQualityResult $result, ?ProductionQualityDisposition $disposition = null, ?int $userId = null, ?string $notes = null): ProductionQualityCheck
    {
        $this->authorize($execution, 'placeQualityHold', $userId);

        return DB::transaction(function () use ($execution, $stage, $result, $disposition, $userId, $notes): ProductionQualityCheck {
            $locked = $this->lockExecution($execution);
            $check = ProductionQualityCheck::query()->create([
                'production_operation_execution_id' => $locked->id,
                'stage' => $stage,
                'result' => $result,
                'disposition' => $disposition,
                'checked_by' => $userId,
                'checked_at' => now(),
                'notes' => $notes,
                'idempotency_key' => $this->idempotencyKey('quality', [$locked->id, $stage->value, $result->value, now()->timestamp]),
            ]);

            if ($result === ProductionQualityResult::Failed || $disposition === ProductionQualityDisposition::Hold) {
                $this->placeQualityHold($locked, $notes ?? 'Quality check requires hold.', $userId);
            }

            return $check;
        });
    }

    public function placeQualityHold(ProductionOperationExecution $execution, string $reason, ?int $userId = null): ProductionQualityHold
    {
        $hold = $execution->qualityHolds()->create([
            'status' => 'active',
            'reason' => $reason,
            'placed_by' => $userId,
            'placed_at' => now(),
        ]);

        $this->recordEvent($execution, 'quality_hold_placed', $execution->status, $execution->status, $userId, $execution->operator_employee_id, ['reason' => $reason]);

        return $hold;
    }

    public function releaseQualityHold(ProductionQualityHold $hold, string $reason, ?int $userId = null): ProductionQualityHold
    {
        $this->authorize($hold->execution, 'releaseQualityHold', $userId);

        return DB::transaction(function () use ($hold, $reason, $userId): ProductionQualityHold {
            $locked = ProductionQualityHold::query()->lockForUpdate()->findOrFail($hold->id);
            $locked->update([
                'status' => 'released',
                'released_by' => $userId,
                'released_at' => now(),
                'release_reason' => $reason,
            ]);

            $this->recordEvent($locked->execution, 'quality_hold_released', $locked->execution->status, $locked->execution->status, $userId, $locked->execution->operator_employee_id, ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    private function transitionWithTimer(ProductionOperationExecution $execution, ProductionOperationExecutionStatus $target, string $timeType, ?int $userId, ?string $idempotencyKey = null): ProductionOperationExecution
    {
        return DB::transaction(function () use ($execution, $target, $timeType, $userId, $idempotencyKey): ProductionOperationExecution {
            $locked = $this->lockExecution($execution);
            $this->assertTransition($locked, $target);
            $this->assertInterOrderDependenciesReady($locked);
            $this->assertNoOpenTimer($locked, $timeType);
            $this->assertNoOverlap($locked, $timeType);

            ProductionTimeEntry::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey ?? $this->idempotencyKey('timer-start', [$locked->id, $timeType, $locked->status->value])],
                [
                    'production_operation_execution_id' => $locked->id,
                    'employee_id' => $locked->operator_employee_id,
                    'machine_center_id' => $locked->machine_center_id,
                    'time_type' => $timeType,
                    'started_at' => now(),
                    'created_by' => $userId,
                ],
            );

            $from = $locked->status;
            $locked->update(['status' => $target]);
            $this->recordEvent($locked, "{$timeType}_started", $from, $target, $userId, $locked->operator_employee_id);

            return $locked->fresh();
        });
    }

    private function stopTimerAndTransition(ProductionOperationExecution $execution, ProductionOperationExecutionStatus $target, string $timeType, ?int $userId): ProductionOperationExecution
    {
        return DB::transaction(function () use ($execution, $target, $timeType, $userId): ProductionOperationExecution {
            $locked = $this->lockExecution($execution);
            $this->assertTransition($locked, $target);
            $duration = $this->closeOpenTimer($locked, $timeType, $userId);

            $from = $locked->status;
            $locked->update(['status' => $target]);
            $this->recordEvent($locked, "{$timeType}_stopped", $from, $target, $userId, $locked->operator_employee_id, ['duration_seconds' => $duration]);

            return $locked->fresh();
        });
    }

    private function createJournalFromExecution(ProductionOperationExecution $execution, ?int $userId): ProductionJournalBatch
    {
        if ($execution->production_journal_batch_id) {
            return $execution->journalBatch;
        }

        $execution->loadMissing('productionOrder.components.item', 'productionOrder.components.location', 'productionOrder.item', 'routingLine');

        $template = ProductionJournalTemplate::query()->where('is_active', true)->orderBy('id')->first();
        if (! $template) {
            throw new RuntimeException('No active production journal template exists for shop-floor journal creation.');
        }

        $lineCreatorId = $userId ?? auth()->id() ?? $execution->created_by;

        $batch = ProductionJournalBatch::query()->create([
            'template_id' => $template->id,
            'name' => 'SFC-'.$execution->id,
            'description' => 'Shop floor execution '.$execution->id,
            'status' => JournalBatchStatus::OPEN,
            'assigned_user_id' => $userId,
            'production_order_id' => $execution->production_order_id,
        ]);

        $lineNo = 10000;
        foreach ($execution->productionOrder->components as $component) {
            $quantityBase = (float) ($component->remaining_quantity ?: $component->expected_quantity_base ?: $component->expected_quantity);

            if ($quantityBase <= 0) {
                continue;
            }

            ProductionJournalLine::query()->create([
                'batch_id' => $batch->id,
                'line_no' => $lineNo,
                'posting_date' => $execution->posting_date ?? now()->toDateString(),
                'entry_type' => ProductionJournalEntryType::Consumption,
                'production_order_id' => $execution->production_order_id,
                'production_order_no' => $execution->productionOrder->document_number,
                'routing_line_no' => $execution->routingLine?->line_number,
                'routing_line_id' => $execution->routing_line_id,
                'production_operation_execution_id' => $execution->id,
                'item_id' => $component->item_id,
                'item_no' => $component->item?->item_code,
                'description' => $component->description ?? $component->item?->description,
                'unit_of_measure_code' => $component->unit_of_measure_code,
                'quantity' => $quantityBase,
                'quantity_base' => $quantityBase,
                'location_id' => $component->location?->id ?? $execution->location_id,
                'work_center_id' => $execution->work_center_id,
                'machine_center_id' => $execution->machine_center_id,
                'unit_cost' => $component->unit_cost ?: $component->item?->unit_cost,
                'created_by' => $lineCreatorId,
                'line_status' => JournalLineStatus::OPEN,
                'shop_floor_idempotency_key' => $this->idempotencyKey('journal-consumption', [$execution->id, $component->id]),
            ]);
            $lineNo += 10000;
        }

        if ((int) $execution->setup_seconds + (int) $execution->run_seconds > 0) {
            ProductionJournalLine::query()->create([
                'batch_id' => $batch->id,
                'line_no' => $lineNo,
                'posting_date' => $execution->posting_date ?? now()->toDateString(),
                'entry_type' => ProductionJournalEntryType::Capacity,
                'production_order_id' => $execution->production_order_id,
                'production_order_no' => $execution->productionOrder->document_number,
                'routing_line_no' => $execution->routingLine?->line_number,
                'routing_line_id' => $execution->routing_line_id,
                'production_operation_execution_id' => $execution->id,
                'quantity' => 0,
                'quantity_base' => 0,
                'work_center_id' => $execution->work_center_id,
                'machine_center_id' => $execution->machine_center_id,
                'setup_time' => $this->secondsToHours((int) $execution->setup_seconds),
                'run_time' => $this->secondsToHours((int) $execution->run_seconds),
                'stop_time' => $this->secondsToHours((int) $execution->downtime_seconds),
                'output_quantity' => $execution->good_quantity,
                'scrap_quantity' => $execution->scrap_quantity,
                'created_by' => $lineCreatorId,
                'line_status' => JournalLineStatus::OPEN,
                'shop_floor_idempotency_key' => $this->idempotencyKey('journal-capacity', [$execution->id]),
            ]);
            $lineNo += 10000;
        }

        if ((float) $execution->good_quantity > 0) {
            ProductionJournalLine::query()->create([
                'batch_id' => $batch->id,
                'line_no' => $lineNo,
                'posting_date' => $execution->posting_date ?? now()->toDateString(),
                'entry_type' => ProductionJournalEntryType::Output,
                'production_order_id' => $execution->production_order_id,
                'production_order_no' => $execution->productionOrder->document_number,
                'routing_line_no' => $execution->routingLine?->line_number,
                'routing_line_id' => $execution->routing_line_id,
                'production_operation_execution_id' => $execution->id,
                'item_id' => $execution->productionOrder->item_id,
                'item_no' => $execution->productionOrder->item?->item_code,
                'description' => $execution->productionOrder->item?->description,
                'unit_of_measure_code' => $execution->productionOrder->unit_of_measure_code,
                'quantity' => $execution->good_quantity,
                'quantity_base' => $execution->good_quantity,
                'location_id' => $execution->location_id,
                'work_center_id' => $execution->work_center_id,
                'machine_center_id' => $execution->machine_center_id,
                'created_by' => $lineCreatorId,
                'line_status' => JournalLineStatus::OPEN,
                'shop_floor_idempotency_key' => $this->idempotencyKey('journal-output', [$execution->id]),
            ]);
        }

        $execution->update(['production_journal_batch_id' => $batch->id]);

        return $batch;
    }

    private function lockExecution(ProductionOperationExecution $execution): ProductionOperationExecution
    {
        return ProductionOperationExecution::query()
            ->with(['productionOrder.item', 'routingLine', 'journalBatch'])
            ->lockForUpdate()
            ->findOrFail($execution->id);
    }

    private function authorize(ProductionOperationExecution $execution, string $ability, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $user = User::query()->find($userId);
        if (! $user || ! $user->can($ability, $execution)) {
            throw new RuntimeException("User is not authorized to {$ability} this production operation execution.");
        }
    }

    private function assertTransition(ProductionOperationExecution $execution, ProductionOperationExecutionStatus $target): void
    {
        if (! $execution->status->canTransitionTo($target)) {
            throw new RuntimeException("Cannot transition operation execution from {$execution->status->value} to {$target->value}.");
        }
    }

    private function closeOpenTimer(ProductionOperationExecution $execution, string $timeType, ?int $userId): int
    {
        $entry = $execution->timeEntries()
            ->where('time_type', $timeType)
            ->whereNull('ended_at')
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (! $entry) {
            throw new RuntimeException("No open {$timeType} timer exists for this operation.");
        }

        $endedAt = now();
        $duration = (int) max(0, $entry->started_at?->diffInSeconds($endedAt, false) ?? 0);
        $entry->update([
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'created_by' => $entry->created_by ?? $userId,
        ]);

        $this->applyDuration($execution, $timeType, $duration, true);

        return $duration;
    }

    private function applyDuration(ProductionOperationExecution $execution, string $timeType, int $duration, bool $includeLabour): void
    {
        $updates = match ($timeType) {
            'setup' => ['setup_seconds' => (int) $execution->setup_seconds + $duration],
            'run' => ['run_seconds' => (int) $execution->run_seconds + $duration],
            default => [],
        };

        if ($includeLabour) {
            $updates['labour_seconds'] = (int) $execution->labour_seconds + $duration;
        }

        if ($execution->machine_center_id && in_array($timeType, ['setup', 'run'], true)) {
            $updates['machine_seconds'] = (int) $execution->machine_seconds + $duration;
        }

        if ($updates !== []) {
            $execution->update($updates);
            $execution->refresh();
        }
    }

    private function assertNoOpenTimer(ProductionOperationExecution $execution, string $timeType): void
    {
        if ($execution->timeEntries()->where('time_type', $timeType)->whereNull('ended_at')->exists()) {
            throw new RuntimeException("An open {$timeType} timer already exists for this operation.");
        }
    }

    private function assertNoOverlap(ProductionOperationExecution $execution, string $timeType): void
    {
        if ($execution->operator_employee_id && ProductionTimeEntry::query()
            ->where('employee_id', $execution->operator_employee_id)
            ->whereNull('ended_at')
            ->where('production_operation_execution_id', '!=', $execution->id)
            ->exists()) {
            throw new RuntimeException('The operator already has an open production time entry.');
        }

        if ($execution->machine_center_id && ProductionTimeEntry::query()
            ->where('machine_center_id', $execution->machine_center_id)
            ->where('exclusive_machine', true)
            ->whereNull('ended_at')
            ->where('production_operation_execution_id', '!=', $execution->id)
            ->exists()) {
            throw new RuntimeException('The machine already has an open exclusive production time entry.');
        }
    }

    private function ensureNoActiveQualityHold(ProductionOperationExecution $execution): void
    {
        if ($execution->activeQualityHolds()->exists()) {
            throw new RuntimeException('Active quality holds must be released before completing the operation.');
        }
    }

    private function assertInterOrderDependenciesReady(ProductionOperationExecution $execution): void
    {
        $readiness = $this->dependencyReadinessService->readinessForExecution($execution);

        if (! $readiness->ready) {
            throw new RuntimeException('Operation cannot start: '.$readiness->reason());
        }
    }

    private function enforcePreviousOperationComplete(ProductionOperationExecution $execution): void
    {
        $previous = ProductionOrderRoutingLine::query()
            ->where('production_order_id', $execution->production_order_id)
            ->where('line_number', '<', $execution->routingLine->line_number)
            ->orderByDesc('line_number')
            ->first();

        if (! $previous) {
            return;
        }

        $completed = ProductionOperationExecution::query()
            ->where('routing_line_id', $previous->id)
            ->whereIn('status', [
                ProductionOperationExecutionStatus::Completed,
                ProductionOperationExecutionStatus::Submitted,
                ProductionOperationExecutionStatus::Posted,
            ])
            ->exists();

        if (! $completed) {
            throw new RuntimeException('Previous routing operation must be completed before this operation can be completed.');
        }
    }

    private function recordEvent(ProductionOperationExecution $execution, string $eventType, ProductionOperationExecutionStatus|string|null $from, ProductionOperationExecutionStatus|string|null $to, ?int $userId, ?int $employeeId, array $metadata = []): void
    {
        ProductionOperationExecutionEvent::query()->create([
            'production_operation_execution_id' => $execution->id,
            'event_type' => $eventType,
            'from_status' => $from instanceof ProductionOperationExecutionStatus ? $from->value : $from,
            'to_status' => $to instanceof ProductionOperationExecutionStatus ? $to->value : $to,
            'occurred_at' => now(),
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'idempotency_key' => $this->idempotencyKey('event', [$execution->id, $eventType, microtime(true)]),
            'metadata' => $metadata,
        ]);

        $this->audit($execution, $eventType, $userId, $metadata);
    }

    private function audit(ProductionOperationExecution $execution, string $action, ?int $userId, array $metadata = []): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'shop_floor',
            action: $action,
            auditable: $execution,
            documentType: 'SHOP_FLOOR_EXECUTION',
            documentNo: (string) $execution->id,
            userId: $userId,
            description: "Shop floor execution {$action}",
            metadata: $metadata,
        );
    }

    private function idempotencyKey(string $scope, array $parts): string
    {
        return hash('sha256', $scope.'|'.implode('|', array_map(fn (mixed $part): string => (string) $part, $parts)));
    }

    private function secondsToHours(int $seconds): float
    {
        return round($seconds / 3600, 6);
    }
}
