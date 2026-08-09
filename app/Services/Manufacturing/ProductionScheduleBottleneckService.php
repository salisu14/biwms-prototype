<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionOperationSchedule;
use App\Models\Manufacturing\ProductionSchedule;
use App\Models\Manufacturing\WorkCenter;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProductionScheduleBottleneckService
{
    public function __construct(private readonly ProductionCapacityCalendarService $calendarService) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forSchedule(ProductionSchedule $schedule, float $thresholdPercent = 85.0): array
    {
        return $this->forPayloads($schedule->operationSchedules()->with('workCenter')->get()->map(fn (ProductionOperationSchedule $operation): array => [
            'work_center_id' => $operation->work_center_id,
            'machine_center_id' => $operation->machine_center_id,
            'scheduled_start_at' => $operation->scheduled_start_at,
            'scheduled_finish_at' => $operation->scheduled_finish_at,
            'capacity_required_minutes' => (float) $operation->capacity_required_minutes,
            'production_order_id' => $operation->production_order_id,
        ])->all(), $thresholdPercent);
    }

    /**
     * @param  array<int, array<string, mixed>>  $operationPayloads
     * @return array<int, array<string, mixed>>
     */
    public function forPayloads(array $operationPayloads, float $thresholdPercent = 85.0): array
    {
        $grouped = collect($operationPayloads)->groupBy(function (array $payload): string {
            $date = Carbon::parse($payload['scheduled_start_at'])->toDateString();

            return ($payload['work_center_id'] ?? 'none').'|'.($payload['machine_center_id'] ?? 'pool').'|'.$date;
        });

        return $grouped
            ->map(function (Collection $operations, string $key) use ($thresholdPercent): ?array {
                [$workCenterId, $machineCenterId, $date] = explode('|', $key);
                $workCenter = WorkCenter::query()->find($workCenterId);

                if (! $workCenter) {
                    return null;
                }

                $available = $this->calendarService->availableMinutesFor($workCenter, Carbon::parse($date));
                $required = (float) $operations->sum(fn (array $operation): float => (float) $operation['capacity_required_minutes']);
                $utilization = $available > 0 ? ($required / $available) * 100 : 999.0;

                if ($utilization < $thresholdPercent) {
                    return null;
                }

                return [
                    'resource_type' => $machineCenterId === 'pool' ? 'work_center' : 'machine_center',
                    'work_center_id' => (int) $workCenterId,
                    'machine_center_id' => $machineCenterId === 'pool' ? null : (int) $machineCenterId,
                    'date' => $date,
                    'available_capacity_minutes' => $available,
                    'required_capacity_minutes' => $required,
                    'utilization_percent' => round($utilization, 2),
                    'affected_order_ids' => $operations->pluck('production_order_id')->unique()->values()->all(),
                    'suggested_action' => $utilization > 100 ? 'Add capacity, move work, or use alternate resources.' : 'Monitor high utilization.',
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
