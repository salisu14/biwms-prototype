<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ProductionScheduleStatus;
use App\Models\Manufacturing\ProductionOperationSchedule;
use App\Models\Manufacturing\ProductionSchedule;
use App\Models\Manufacturing\ProductionSchedulingException;
use App\Services\Manufacturing\ProductionCapacityCalendarService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:production-schedule-reconcile {--json : Output machine-readable JSON} {--details : Show detailed diagnostic rows} {--export= : Write the JSON report to a file path}')]
#[Description('Report APS Lite production schedule consistency issues without writing data.')]
class BiwmsProductionScheduleReconcile extends Command
{
    public function __construct(private readonly ProductionCapacityCalendarService $calendarService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('production_schedules') || ! Schema::hasTable('production_operation_schedules')) {
            $report = [
                'schema_pending' => [[
                    'classification' => 'schema_pending',
                    'severity' => 'warning',
                    'message' => 'Phase 2C production scheduling tables are not present on this database connection.',
                    'remediation' => 'Run the pending production schedule migrations before using Phase 2C reconciliation.',
                ]],
            ];

            return $this->renderReport($report);
        }

        $report = [
            'schedule_operation_missing_production_order' => $this->operationMissingProductionOrder(),
            'schedule_operation_missing_routing_line' => $this->operationMissingRoutingLine(),
            'overlapping_exclusive_machine_assignment' => $this->overlappingMachineAssignments(),
            'work_center_concurrent_capacity_exceeded' => $this->workCenterCapacityExceeded(),
            'scheduled_operation_before_predecessor' => $this->operationBeforePredecessor(),
            'scheduled_operation_outside_calendar' => $this->operationOutsideCalendar(),
            'completed_operation_still_rescheduled' => $this->completedOperationRescheduled(),
            'duplicate_active_schedule_assignment' => $this->duplicateActiveAssignments(),
            'approved_schedule_without_operations' => $this->approvedScheduleWithoutOperations(),
            'schedule_after_order_due_date' => $this->scheduleAfterDueDate(),
            'negative_or_zero_duration' => $this->negativeOrZeroDuration(),
            'orphan_exception' => $this->orphanExceptions(),
        ];

        return $this->renderReport($report);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationMissingProductionOrder(): array
    {
        return ProductionOperationSchedule::query()
            ->whereDoesntHave('productionOrder')
            ->get()
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('schedule_operation_missing_production_order', 'critical', $operation, 'Scheduled operation references a missing Production Order.', 'Cancel or regenerate the schedule version.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationMissingRoutingLine(): array
    {
        return ProductionOperationSchedule::query()
            ->whereDoesntHave('routingLine')
            ->get()
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('schedule_operation_missing_routing_line', 'critical', $operation, 'Scheduled operation references a missing routing line.', 'Regenerate schedule from current routing lines.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overlappingMachineAssignments(): array
    {
        return ProductionOperationSchedule::query()
            ->whereNotNull('machine_center_id')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->get()
            ->filter(fn (ProductionOperationSchedule $operation): bool => ProductionOperationSchedule::query()
                ->where('id', '!=', $operation->id)
                ->where('machine_center_id', $operation->machine_center_id)
                ->whereNotIn('status', ['cancelled', 'rescheduled'])
                ->where('scheduled_start_at', '<', $operation->scheduled_finish_at)
                ->where('scheduled_finish_at', '>', $operation->scheduled_start_at)
                ->exists())
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('overlapping_exclusive_machine_assignment', 'critical', $operation, 'Exclusive machine has overlapping scheduled operations.', 'Reschedule one operation or assign an approved alternate machine.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workCenterCapacityExceeded(): array
    {
        return ProductionOperationSchedule::query()
            ->with('workCenter')
            ->whereNull('machine_center_id')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->get()
            ->filter(function (ProductionOperationSchedule $operation): bool {
                if (! $operation->workCenter) {
                    return false;
                }

                $overlapCount = ProductionOperationSchedule::query()
                    ->where('id', '!=', $operation->id)
                    ->whereNull('machine_center_id')
                    ->where('work_center_id', $operation->work_center_id)
                    ->whereNotIn('status', ['cancelled', 'rescheduled'])
                    ->where('scheduled_start_at', '<', $operation->scheduled_finish_at)
                    ->where('scheduled_finish_at', '>', $operation->scheduled_start_at)
                    ->count();

                return $overlapCount + 1 > $this->calendarService->concurrentCapacity($operation->workCenter, null);
            })
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('work_center_concurrent_capacity_exceeded', 'critical', $operation, 'Work Center concurrent capacity is exceeded.', 'Move one operation or increase configured capacity.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationBeforePredecessor(): array
    {
        return ProductionOperationSchedule::query()
            ->with('routingLine.downstreamDependencies')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->get()
            ->filter(fn (ProductionOperationSchedule $operation): bool => $operation->routingLine?->downstreamDependencies
                ->contains(fn ($dependency): bool => $dependency->upstream_routing_line_id && ProductionOperationSchedule::query()
                    ->where('production_schedule_id', $operation->production_schedule_id)
                    ->where('production_order_routing_line_id', $dependency->upstream_routing_line_id)
                    ->where('scheduled_finish_at', '>', $operation->scheduled_start_at)
                    ->exists()))
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('scheduled_operation_before_predecessor', 'critical', $operation, 'Operation starts before an upstream dependency is scheduled to finish.', 'Regenerate dependency-aware schedule.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function operationOutsideCalendar(): array
    {
        return ProductionOperationSchedule::query()
            ->with('workCenter')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->get()
            ->filter(fn (ProductionOperationSchedule $operation): bool => ! $operation->workCenter || ! $this->calendarService->calendarFor($operation->workCenter, $operation->scheduled_start_at))
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('scheduled_operation_outside_calendar', 'warning', $operation, 'Operation is scheduled outside a configured working calendar.', 'Add calendar capacity or reschedule.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function completedOperationRescheduled(): array
    {
        return ProductionOperationSchedule::query()
            ->where('status', 'completed')
            ->where('frozen', false)
            ->get()
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('completed_operation_still_rescheduled', 'warning', $operation, 'Completed scheduled operation is not frozen.', 'Freeze completed operations before future rescheduling.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function duplicateActiveAssignments(): array
    {
        return ProductionOperationSchedule::query()
            ->selectRaw('min(id) as id, production_schedule_id, production_order_routing_line_id, count(*) as duplicate_count')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->groupBy('production_schedule_id', 'production_order_routing_line_id')
            ->havingRaw('count(*) > 1')
            ->get()
            ->map(fn ($row): array => [
                'classification' => 'duplicate_active_schedule_assignment',
                'severity' => 'critical',
                'operation_schedule_id' => (int) $row->id,
                'production_schedule_id' => (int) $row->production_schedule_id,
                'routing_line_id' => (int) $row->production_order_routing_line_id,
                'duplicate_count' => (int) $row->duplicate_count,
                'remediation' => 'Supersede the schedule and regenerate assignments.',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function approvedScheduleWithoutOperations(): array
    {
        return ProductionSchedule::query()
            ->where('status', ProductionScheduleStatus::Approved->value)
            ->whereDoesntHave('operationSchedules')
            ->get()
            ->map(fn (ProductionSchedule $schedule): array => [
                'classification' => 'approved_schedule_without_operations',
                'severity' => 'critical',
                'production_schedule_id' => $schedule->id,
                'message' => 'Approved schedule has no operation assignments.',
                'remediation' => 'Cancel or regenerate the schedule before planner use.',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduleAfterDueDate(): array
    {
        return ProductionOperationSchedule::query()
            ->with('productionOrder')
            ->whereNotIn('status', ['cancelled', 'rescheduled'])
            ->get()
            ->filter(fn (ProductionOperationSchedule $operation): bool => $operation->productionOrder?->due_date && $operation->scheduled_finish_at->greaterThan($operation->productionOrder->due_date->endOfDay()))
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('schedule_after_order_due_date', 'warning', $operation, 'Operation finishes after order due date.', 'Review capacity, priority, or due date.'))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function negativeOrZeroDuration(): array
    {
        return ProductionOperationSchedule::query()
            ->whereColumn('scheduled_finish_at', '<=', 'scheduled_start_at')
            ->get()
            ->map(fn (ProductionOperationSchedule $operation): array => $this->finding('negative_or_zero_duration', 'critical', $operation, 'Scheduled operation duration is zero or negative.', 'Regenerate from valid routing times.'))
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orphanExceptions(): array
    {
        return ProductionSchedulingException::query()
            ->whereDoesntHave('schedule')
            ->get()
            ->map(fn ($exception): array => [
                'classification' => 'orphan_exception',
                'severity' => 'warning',
                'exception_id' => $exception->id,
                'message' => 'Scheduling exception references a missing schedule.',
                'remediation' => 'Review and archive orphaned diagnostics.',
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(string $classification, string $severity, ProductionOperationSchedule $operation, string $message, string $remediation): array
    {
        return [
            'classification' => $classification,
            'severity' => $severity,
            'operation_schedule_id' => $operation->id,
            'production_schedule_id' => $operation->production_schedule_id,
            'production_order_id' => $operation->production_order_id,
            'routing_line_id' => $operation->production_order_routing_line_id,
            'message' => $message,
            'remediation' => $remediation,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $report
     */
    private function renderReport(array $report): int
    {
        if ($exportPath = $this->option('export')) {
            $absolutePath = str_starts_with((string) $exportPath, DIRECTORY_SEPARATOR) ? (string) $exportPath : base_path((string) $exportPath);
            File::ensureDirectoryExists(dirname($absolutePath));
            File::put($absolutePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('BIWMS Production Schedule Reconciliation');
        $this->line('Mode: report-only. No schedule, execution, ledger, or posting records were changed.');
        if ($exportPath) {
            $this->line("Exported JSON report to {$exportPath}.");
        }
        $this->newLine();

        foreach ($report as $label => $findings) {
            $this->line(str($label)->replace('_', ' ')->title().': '.count($findings));

            if (! $this->option('details')) {
                continue;
            }

            foreach ($findings as $finding) {
                $this->line(' - ['.$finding['severity'].'] '.$finding['classification'].' '.json_encode($finding, JSON_UNESCAPED_SLASHES));
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
