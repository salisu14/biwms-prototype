<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ProductionOperationScheduleStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionScheduleStatus;
use App\Enums\ProductionSchedulingExceptionSeverity;
use App\Enums\ProductionSchedulingExceptionType;
use App\Enums\ProductionSchedulingMode;
use App\Models\Manufacturing\ProductionOperationDependency;
use App\Models\Manufacturing\ProductionOperationSchedule;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\ProductionSchedule;
use App\Models\Manufacturing\ProductionScheduleLine;
use App\Models\Manufacturing\ProductionSchedulingException;
use App\Support\DecimalMath;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ProductionSchedulingService
{
    public function __construct(
        private readonly ProductionCapacityCalendarService $calendarService,
        private readonly ProductionAlternateResourceSelectionService $alternateResourceSelectionService,
        private readonly ProductionOperationDependencyReadinessService $readinessService,
        private readonly ProductionScheduleBottleneckService $bottleneckService,
    ) {}

    /**
     * @param  array{production_order_ids?: array<int, int>, horizon_start_at?: mixed, horizon_end_at?: mixed, mode?: string|ProductionSchedulingMode, preview?: bool, generated_by?: int|null, schedule_no?: string|null, name?: string|null, freeze_horizon_minutes?: int|null}  $options
     */
    public function generate(array $options): ProductionSchedulingResult
    {
        $horizonStart = Carbon::parse($options['horizon_start_at'] ?? now())->seconds(0);
        $horizonEnd = Carbon::parse($options['horizon_end_at'] ?? $horizonStart->copy()->addDays(7))->seconds(0);
        $mode = $options['mode'] instanceof ProductionSchedulingMode
            ? $options['mode']
            : ProductionSchedulingMode::from((string) ($options['mode'] ?? ProductionSchedulingMode::Forward->value));
        $preview = (bool) ($options['preview'] ?? false);

        if ($horizonEnd->lessThanOrEqualTo($horizonStart)) {
            throw new RuntimeException('Production scheduling horizon end must be after the horizon start.');
        }

        $orders = $this->candidateOrders($options, $horizonStart, $horizonEnd);

        if ($preview) {
            return $this->buildSchedule(null, $orders, $horizonStart, $horizonEnd, $mode, true);
        }

        return DB::transaction(function () use ($options, $orders, $horizonStart, $horizonEnd, $mode): ProductionSchedulingResult {
            $schedule = ProductionSchedule::query()->create([
                'schedule_no' => $options['schedule_no'] ?? 'APS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'name' => $options['name'] ?? 'APS Lite '.now()->format('Y-m-d H:i'),
                'horizon_start_at' => $horizonStart,
                'horizon_end_at' => $horizonEnd,
                'status' => ProductionScheduleStatus::Generated,
                'scheduling_mode' => $mode,
                'planning_version' => $this->nextPlanningVersion(),
                'freeze_horizon_minutes' => (int) ($options['freeze_horizon_minutes'] ?? 480),
                'generated_by' => $options['generated_by'] ?? auth()->id(),
                'generated_at' => now(),
                'metadata' => [
                    'phase' => '2c',
                    'candidate_order_count' => $orders->count(),
                    'algorithm' => 'deterministic_aps_lite',
                ],
            ]);

            $result = $this->buildSchedule($schedule, $orders, $horizonStart, $horizonEnd, $mode, false);
            $schedule->forceFill(['summary' => $result->summary])->save();

            return $result;
        });
    }

    public function approve(ProductionSchedule $schedule, ?int $userId = null): ProductionSchedule
    {
        if (! in_array($schedule->status, [ProductionScheduleStatus::Generated, ProductionScheduleStatus::Reviewed], true)) {
            throw new RuntimeException('Only generated or reviewed schedules can be approved.');
        }

        $schedule->forceFill([
            'status' => ProductionScheduleStatus::Approved,
            'approved_by' => $userId ?? auth()->id(),
            'approved_at' => now(),
        ])->save();

        return $schedule->fresh();
    }

    /**
     * @param  array{reason: string, override_freeze?: bool, generated_by?: int|null}  $options
     */
    public function reschedule(ProductionSchedule $schedule, array $options = []): ProductionSchedulingResult
    {
        if ($schedule->status === ProductionScheduleStatus::Cancelled) {
            throw new RuntimeException('Cancelled schedules cannot be rescheduled.');
        }

        $now = now();
        $freezeUntil = $now->copy()->addMinutes((int) $schedule->freeze_horizon_minutes);
        $frozenMoved = $schedule->operationSchedules()
            ->where('scheduled_start_at', '<=', $freezeUntil)
            ->whereNotIn('status', [
                ProductionOperationScheduleStatus::Completed->value,
                ProductionOperationScheduleStatus::Started->value,
                ProductionOperationScheduleStatus::Cancelled->value,
            ])
            ->exists();

        if ($frozenMoved && ! (bool) ($options['override_freeze'] ?? false)) {
            throw new RuntimeException('Schedule contains operations inside the freeze horizon. Provide an override reason before rescheduling.');
        }

        $schedule->forceFill([
            'status' => ProductionScheduleStatus::Superseded,
            'superseded_by_schedule_id' => null,
            'metadata' => array_merge($schedule->metadata ?? [], [
                'rescheduled_at' => now()->toIso8601String(),
                'reschedule_reason' => $options['reason'] ?? 'planner_reschedule',
            ]),
        ])->save();

        $orderIds = $schedule->lines()->pluck('production_order_id')->all();

        $result = $this->generate([
            'production_order_ids' => $orderIds,
            'horizon_start_at' => $schedule->horizon_start_at,
            'horizon_end_at' => $schedule->horizon_end_at,
            'mode' => $schedule->scheduling_mode,
            'generated_by' => $options['generated_by'] ?? auth()->id(),
            'freeze_horizon_minutes' => $schedule->freeze_horizon_minutes,
            'name' => ($schedule->name ?: $schedule->schedule_no).' Reschedule',
        ]);

        if ($result->schedule) {
            $schedule->forceFill(['superseded_by_schedule_id' => $result->schedule->id])->save();
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return Collection<int, ProductionOrder>
     */
    private function candidateOrders(array $options, CarbonInterface $horizonStart, CarbonInterface $horizonEnd): Collection
    {
        $query = ProductionOrder::query()
            ->with([
                'routingLines.workCenter.calendarEntries',
                'routingLines.machineCenter.workCenter.calendarEntries',
                'routingLines.downstreamDependencies.upstreamRoutingLine',
                'components.item',
                'productionHierarchy',
            ])
            ->whereIn('status', [
                ProductionOrderStatus::PLANNED->value,
                ProductionOrderStatus::FIRM_PLANNED->value,
                ProductionOrderStatus::RELEASED->value,
            ]);

        if ($ids = $options['production_order_ids'] ?? null) {
            $query->whereIn('id', $ids);
        } else {
            $query->where(function ($query) use ($horizonStart, $horizonEnd): void {
                $query->whereNull('due_date')
                    ->orWhereBetween('due_date', [$horizonStart->toDateString(), $horizonEnd->toDateString()]);
            });
        }

        return $query
            ->get()
            ->sortBy([
                fn (ProductionOrder $a, ProductionOrder $b): int => ((int) ($a->priority ?? 100)) <=> ((int) ($b->priority ?? 100)),
                fn (ProductionOrder $a, ProductionOrder $b): int => strcmp((string) ($a->due_date?->toDateString() ?? '9999-12-31'), (string) ($b->due_date?->toDateString() ?? '9999-12-31')),
                fn (ProductionOrder $a, ProductionOrder $b): int => strcmp((string) ($a->starting_date_time?->toIso8601String() ?? ''), (string) ($b->starting_date_time?->toIso8601String() ?? '')),
                fn (ProductionOrder $a, ProductionOrder $b): int => strcmp((string) $a->document_number, (string) $b->document_number),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, ProductionOrder>  $orders
     */
    private function buildSchedule(?ProductionSchedule $schedule, Collection $orders, CarbonInterface $horizonStart, CarbonInterface $horizonEnd, ProductionSchedulingMode $mode, bool $preview): ProductionSchedulingResult
    {
        $operationPayloads = [];
        $exceptionPayloads = [];
        $scheduledByRoutingLineId = [];
        $lineNumber = 10000;
        $sequence = 10000;

        foreach ($orders as $order) {
            $routingLines = $order->routingLines()->with(['workCenter.calendarEntries', 'machineCenter.workCenter.calendarEntries', 'downstreamDependencies.upstreamRoutingLine'])->orderBy('line_number')->get();
            $this->appendMaterialAvailabilityExceptions($schedule, $order, $exceptionPayloads);

            if ($routingLines->isEmpty()) {
                $exceptionPayloads[] = $this->exceptionPayload($schedule, null, $order, null, ProductionSchedulingExceptionType::NoRouting, 'Production order has no routing operations.', 'Add routing lines before scheduling.');

                continue;
            }

            $scheduleLine = $this->scheduleLine($schedule, $order, $lineNumber, $preview);
            $lineNumber += 10000;

            if ($mode === ProductionSchedulingMode::Backward) {
                $this->scheduleOrderBackward($schedule, $scheduleLine, $order, $routingLines->reverse()->values(), $horizonStart, $horizonEnd, $preview, $operationPayloads, $exceptionPayloads, $scheduledByRoutingLineId, $sequence);
            } else {
                $this->scheduleOrderForward($schedule, $scheduleLine, $order, $routingLines, $horizonStart, $horizonEnd, $preview, $operationPayloads, $exceptionPayloads, $scheduledByRoutingLineId, $sequence);
            }
        }

        if (! $preview && $schedule) {
            foreach ($exceptionPayloads as $exceptionPayload) {
                ProductionSchedulingException::query()->create($exceptionPayload);
            }
        }

        $bottlenecks = $schedule
            ? $this->bottleneckService->forSchedule($schedule)
            : $this->bottleneckService->forPayloads($operationPayloads);

        $summary = [
            'orders_considered' => $orders->count(),
            'operations_scheduled' => count($operationPayloads),
            'exceptions' => count($exceptionPayloads),
            'late_operations' => collect($operationPayloads)->where('late', true)->count(),
            'bottlenecks' => count($bottlenecks),
            'mode' => $mode->value,
            'preview' => $preview,
        ];

        return new ProductionSchedulingResult($schedule?->fresh(), $operationPayloads, $exceptionPayloads, $bottlenecks, $summary);
    }

    private function scheduleOrderForward(
        ?ProductionSchedule $schedule,
        ?ProductionScheduleLine $scheduleLine,
        ProductionOrder $order,
        Collection $routingLines,
        CarbonInterface $horizonStart,
        CarbonInterface $horizonEnd,
        bool $preview,
        array &$operationPayloads,
        array &$exceptionPayloads,
        array &$scheduledByRoutingLineId,
        int &$sequence,
    ): void {
        $cursor = $order->starting_date_time?->greaterThan($horizonStart) ? $order->starting_date_time->copy() : $horizonStart->copy();
        $predecessor = null;

        foreach ($routingLines as $routingLine) {
            $duration = $this->durationMinutes($routingLine, (float) $order->quantity_base);
            if ($duration <= 0) {
                $exceptionPayloads[] = $this->exceptionPayload($schedule, null, $order, $routingLine, ProductionSchedulingExceptionType::OperationDurationInvalid, 'Operation has zero or negative duration.', 'Maintain setup/run/wait/move time on the routing line.');

                continue;
            }

            $resource = $this->alternateResourceSelectionService->select($routingLine, $cursor, $duration, $horizonEnd);
            $workCenter = $resource['work_center'];

            if (! $workCenter) {
                $exceptionPayloads[] = $this->exceptionPayload($schedule, null, $order, $routingLine, ProductionSchedulingExceptionType::NoValidWorkCenter, 'Operation has no valid Work Center.', 'Assign a Work Center or valid alternate resource.');

                continue;
            }

            $dependencyStart = $this->dependencyEarliestStart($routingLine, $scheduledByRoutingLineId, $exceptionPayloads, $schedule, $order);
            if ($dependencyStart && $dependencyStart->greaterThan($cursor)) {
                $cursor = $dependencyStart;
            }

            $slot = $this->calendarService->nextForwardSlot($workCenter, $resource['machine_center']?->id, $cursor, $duration, $horizonEnd, $schedule?->id);

            if (! $slot) {
                $exceptionPayloads[] = $this->exceptionPayload($schedule, null, $order, $routingLine, ProductionSchedulingExceptionType::CapacityOverload, 'No finite-capacity slot is available in the horizon.', 'Extend the horizon, add capacity, or configure an alternate resource.');

                continue;
            }

            [$start, $finish] = $slot;
            $late = $order->due_date && $finish->greaterThan($order->due_date->endOfDay());
            $payload = $this->operationPayload($schedule, $scheduleLine, $order, $routingLine, $workCenter->id, $resource['machine_center']?->id, $start, $finish, $duration, $sequence, $predecessor?->id, $resource);
            $payload['late'] = (bool) $late;
            $payload['lateness_minutes'] = $late ? $finish->diffInMinutes($order->due_date->endOfDay()) : 0;

            $operationSchedule = $preview ? null : ProductionOperationSchedule::query()->create($payload);
            $operationPayloads[] = $payload;
            $scheduledByRoutingLineId[$routingLine->id] = [
                'start' => $start,
                'finish' => $finish,
                'record' => $operationSchedule,
            ];
            $predecessor = $operationSchedule;
            $cursor = $finish->copy();
            $sequence += 10000;
        }
    }

    private function scheduleOrderBackward(
        ?ProductionSchedule $schedule,
        ?ProductionScheduleLine $scheduleLine,
        ProductionOrder $order,
        Collection $routingLines,
        CarbonInterface $horizonStart,
        CarbonInterface $horizonEnd,
        bool $preview,
        array &$operationPayloads,
        array &$exceptionPayloads,
        array &$scheduledByRoutingLineId,
        int &$sequence,
    ): void {
        $cursor = $order->due_date ? Carbon::parse($order->due_date->toDateString().' 23:59:00') : $horizonEnd->copy();
        $successorStart = null;

        foreach ($routingLines as $routingLine) {
            $duration = $this->durationMinutes($routingLine, (float) $order->quantity_base);
            $resource = $this->alternateResourceSelectionService->select($routingLine, $horizonStart, $duration, $horizonEnd);
            $workCenter = $resource['work_center'];

            if (! $workCenter || $duration <= 0) {
                $exceptionPayloads[] = $this->exceptionPayload($schedule, null, $order, $routingLine, ProductionSchedulingExceptionType::OperationDurationInvalid, 'Operation cannot be backward scheduled.', 'Confirm routing duration and resource assignment.');

                continue;
            }

            if ($successorStart && $successorStart->lessThan($cursor)) {
                $cursor = $successorStart->copy();
            }

            $slot = $this->calendarService->nextBackwardSlot($workCenter, $resource['machine_center']?->id, $cursor, $duration, $horizonStart, $schedule?->id);

            if (! $slot) {
                $exceptionPayloads[] = $this->exceptionPayload($schedule, null, $order, $routingLine, ProductionSchedulingExceptionType::DueDateImpossible, 'No backward finite-capacity slot exists before the required date.', 'Move due date, add capacity, or schedule forward with lateness visibility.');

                continue;
            }

            [$start, $finish] = $slot;
            $payload = $this->operationPayload($schedule, $scheduleLine, $order, $routingLine, $workCenter->id, $resource['machine_center']?->id, $start, $finish, $duration, $sequence, null, $resource);
            $operationSchedule = $preview ? null : ProductionOperationSchedule::query()->create($payload);
            $operationPayloads[] = $payload;
            $scheduledByRoutingLineId[$routingLine->id] = [
                'start' => $start,
                'finish' => $finish,
                'record' => $operationSchedule,
            ];
            $successorStart = $start->copy();
            $sequence += 10000;
        }
    }

    private function durationMinutes(ProductionOrderRoutingLine $routingLine, float $quantityBase): int
    {
        $setupMinutes = $this->timeToMinutes((float) $routingLine->setup_time, (string) $routingLine->setup_time_unit);
        $runMinutes = $this->timeToMinutes((float) $routingLine->run_time, (string) $routingLine->run_time_unit);
        $referenceQuantityBase = max(1.0, (float) ($routingLine->expected_output_quantity ?: $quantityBase ?: 1));
        $runScale = max(1.0, $quantityBase / $referenceQuantityBase);
        $waitMoveMinutes = (float) $routingLine->wait_time + (float) $routingLine->move_time;

        return (int) ceil($setupMinutes + ($runMinutes * $runScale) + $waitMoveMinutes);
    }

    private function timeToMinutes(float $value, string $unit): float
    {
        return match (strtoupper($unit)) {
            'HOURS', 'HOUR', 'HR', 'HRS' => $value * 60,
            'DAYS', 'DAY' => $value * 1440,
            default => $value,
        };
    }

    private function dependencyEarliestStart(ProductionOrderRoutingLine $routingLine, array $scheduledByRoutingLineId, array &$exceptionPayloads, ?ProductionSchedule $schedule, ProductionOrder $order): ?Carbon
    {
        $latest = null;
        $dependencies = ProductionOperationDependency::query()
            ->with('upstreamRoutingLine')
            ->where('downstream_routing_line_id', $routingLine->id)
            ->whereNotIn('status', ['cancelled', 'invalid'])
            ->get();

        foreach ($dependencies as $dependency) {
            $scheduled = $dependency->upstream_routing_line_id ? ($scheduledByRoutingLineId[$dependency->upstream_routing_line_id] ?? null) : null;

            if ($scheduled) {
                $finish = $scheduled['finish'];
                $latest = $latest && $latest->greaterThan($finish) ? $latest : $finish->copy();

                continue;
            }

            $readiness = $this->readinessService->findingForDependency($dependency);
            if ($readiness['classification'] !== 'ready') {
                $exceptionPayloads[] = $this->exceptionPayload(
                    $schedule,
                    null,
                    $order,
                    $routingLine,
                    ProductionSchedulingExceptionType::UpstreamDependencyUnresolved,
                    (string) $readiness['reason'],
                    (string) ($readiness['remediation'] ?? 'Schedule the upstream operation first.'),
                    ['dependency_id' => $dependency->id],
                );
            }
        }

        return $latest;
    }

    private function appendMaterialAvailabilityExceptions(?ProductionSchedule $schedule, ProductionOrder $order, array &$exceptionPayloads): void
    {
        $order->loadMissing('components.item');

        foreach ($order->components as $component) {
            if ($component->is_manufactured_requirement) {
                continue;
            }

            $requiredQuantityBase = (float) ($component->expected_quantity_base ?: $component->expected_quantity ?: 0);
            $availableQuantityBase = (float) ($component->item?->inventory ?? 0);

            if ($requiredQuantityBase <= 0 || $availableQuantityBase >= $requiredQuantityBase) {
                continue;
            }

            $exceptionPayloads[] = $this->exceptionPayload(
                $schedule,
                null,
                $order,
                null,
                ProductionSchedulingExceptionType::MaterialUnavailable,
                "Known component availability is short for {$component->description}.",
                'Review material availability before committing this schedule.',
                [
                    'component_id' => $component->id,
                    'item_id' => $component->item_id,
                    'required_quantity_base' => $requiredQuantityBase,
                    'available_quantity_base' => $availableQuantityBase,
                ],
            );
        }
    }

    private function scheduleLine(?ProductionSchedule $schedule, ProductionOrder $order, int $lineNumber, bool $preview): ?ProductionScheduleLine
    {
        if ($preview || ! $schedule) {
            return null;
        }

        return ProductionScheduleLine::query()->create([
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $order->id,
            'production_hierarchy_id' => $order->productionHierarchy?->id,
            'root_production_order_id' => $order->root_production_order_id ?: $order->id,
            'line_number' => $lineNumber,
            'priority' => (int) ($order->priority ?? 100),
            'due_date' => $order->due_date,
            'quantity_base' => DecimalMath::quantity($order->quantity_base),
            'status' => 'planned',
        ]);
    }

    /**
     * @param  array{work_center: mixed, machine_center: mixed, uses_alternate: bool, reason: array<string, mixed>}  $resource
     * @return array<string, mixed>
     */
    private function operationPayload(?ProductionSchedule $schedule, ?ProductionScheduleLine $scheduleLine, ProductionOrder $order, ProductionOrderRoutingLine $routingLine, int $workCenterId, ?int $machineCenterId, CarbonInterface $start, CarbonInterface $finish, int $durationMinutes, int $sequence, ?int $predecessorId, array $resource): array
    {
        return [
            'production_schedule_id' => $schedule?->id,
            'production_schedule_line_id' => $scheduleLine?->id,
            'production_order_id' => $order->id,
            'production_order_routing_line_id' => $routingLine->id,
            'production_hierarchy_id' => $order->productionHierarchy?->id,
            'root_production_order_id' => $order->root_production_order_id ?: $order->id,
            'work_center_id' => $workCenterId,
            'machine_center_id' => $machineCenterId,
            'predecessor_operation_schedule_id' => $predecessorId,
            'scheduled_start_at' => $start,
            'scheduled_finish_at' => $finish,
            'setup_duration_minutes' => $this->timeToMinutes((float) $routingLine->setup_time, (string) $routingLine->setup_time_unit),
            'run_duration_minutes' => max(0, $durationMinutes - (float) $routingLine->wait_time - (float) $routingLine->move_time),
            'wait_duration_minutes' => (float) $routingLine->wait_time,
            'queue_duration_minutes' => (float) ($routingLine->workCenter?->queue_time ?? 0),
            'quantity_base' => DecimalMath::quantity($order->quantity_base),
            'capacity_required_minutes' => $durationMinutes,
            'sequence' => $sequence,
            'priority' => (int) ($order->priority ?? 100),
            'status' => ProductionOperationScheduleStatus::Planned,
            'planning_source' => 'aps_lite',
            'uses_alternate_resource' => $resource['uses_alternate'],
            'frozen' => false,
            'late' => false,
            'lateness_minutes' => 0,
            'idempotency_key' => hash('sha256', implode('|', [
                'phase-2c-operation-schedule',
                $schedule?->id ?? 'preview',
                $routingLine->id,
                $start->toIso8601String(),
                $finish->toIso8601String(),
            ])),
            'assignment_reason' => $resource['reason'],
            'metadata' => [
                'operation_no' => $routingLine->operation_no,
                'document_number' => $order->document_number,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function exceptionPayload(?ProductionSchedule $schedule, ?ProductionOperationSchedule $operationSchedule, ?ProductionOrder $order, ?ProductionOrderRoutingLine $routingLine, ProductionSchedulingExceptionType $type, string $message, string $suggestedAction, array $metadata = []): array
    {
        return [
            'production_schedule_id' => $schedule?->id,
            'production_operation_schedule_id' => $operationSchedule?->id,
            'production_order_id' => $order?->id,
            'production_order_routing_line_id' => $routingLine?->id,
            'work_center_id' => $routingLine?->work_center_id,
            'machine_center_id' => $routingLine?->machine_center_id,
            'exception_type' => $type,
            'severity' => in_array($type, [ProductionSchedulingExceptionType::NoRouting, ProductionSchedulingExceptionType::CapacityOverload, ProductionSchedulingExceptionType::DueDateImpossible], true)
                ? ProductionSchedulingExceptionSeverity::Critical
                : ProductionSchedulingExceptionSeverity::Warning,
            'status' => 'open',
            'message' => $message,
            'suggested_action' => $suggestedAction,
            'metadata' => $metadata,
        ];
    }

    private function nextPlanningVersion(): int
    {
        return ((int) ProductionSchedule::query()->max('planning_version')) + 1;
    }
}
