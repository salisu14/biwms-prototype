<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionOperationSchedule;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCalendar;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ProductionCapacityCalendarService
{
    public function nextForwardSlot(
        WorkCenter $workCenter,
        ?int $machineCenterId,
        CarbonInterface $earliestStart,
        int $durationMinutes,
        CarbonInterface $horizonEnd,
        ?int $excludeScheduleId = null,
    ): ?array {
        $cursor = $earliestStart->copy()->seconds(0);

        for ($guard = 0; $guard < 500; $guard++) {
            $calendar = $this->calendarFor($workCenter, $cursor);

            if (! $calendar) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            $shiftStart = $this->shiftStart($calendar);
            $shiftEnd = $this->shiftEnd($calendar);
            $start = $cursor->greaterThan($shiftStart) ? $cursor->copy() : $shiftStart;
            $finish = $start->copy()->addMinutes($durationMinutes);

            if ($finish->greaterThan($shiftEnd)) {
                $cursor = $cursor->copy()->addDay()->startOfDay();

                continue;
            }

            if ($finish->greaterThan($horizonEnd)) {
                return null;
            }

            if ($this->capacityAvailable($workCenter, $machineCenterId, $start, $finish, $excludeScheduleId)) {
                return [$start, $finish];
            }

            $cursor = $this->nextConflictingFinish($workCenter->id, $machineCenterId, $start, $finish, $excludeScheduleId)
                ?? $cursor->copy()->addMinutes(15);
        }

        return null;
    }

    public function nextBackwardSlot(
        WorkCenter $workCenter,
        ?int $machineCenterId,
        CarbonInterface $latestFinish,
        int $durationMinutes,
        CarbonInterface $horizonStart,
        ?int $excludeScheduleId = null,
    ): ?array {
        $cursor = $latestFinish->copy()->seconds(0);

        for ($guard = 0; $guard < 500; $guard++) {
            $calendar = $this->calendarFor($workCenter, $cursor);

            if (! $calendar) {
                $cursor = $cursor->copy()->subDay()->endOfDay();

                continue;
            }

            $shiftStart = $this->shiftStart($calendar);
            $shiftEnd = $this->shiftEnd($calendar);
            $finish = $cursor->lessThan($shiftEnd) ? $cursor->copy() : $shiftEnd;
            $start = $finish->copy()->subMinutes($durationMinutes);

            if ($start->lessThan($shiftStart)) {
                $cursor = $cursor->copy()->subDay()->endOfDay();

                continue;
            }

            if ($start->lessThan($horizonStart)) {
                return null;
            }

            if ($this->capacityAvailable($workCenter, $machineCenterId, $start, $finish, $excludeScheduleId)) {
                return [$start, $finish];
            }

            $cursor = $this->previousConflictingStart($workCenter->id, $machineCenterId, $start, $finish, $excludeScheduleId)
                ?? $cursor->copy()->subMinutes(15);
        }

        return null;
    }

    public function calendarFor(WorkCenter $workCenter, CarbonInterface $dateTime): ?WorkCenterCalendar
    {
        return $workCenter->calendarEntries()
            ->whereDate('date', $dateTime->toDateString())
            ->where('is_working_day', true)
            ->first();
    }

    public function availableMinutesFor(WorkCenter $workCenter, CarbonInterface $date): float
    {
        $calendar = $this->calendarFor($workCenter, $date);

        if (! $calendar) {
            return 0.0;
        }

        return max((float) $calendar->capacity, (float) $calendar->available_minutes);
    }

    public function concurrentCapacity(WorkCenter $workCenter, ?int $machineCenterId): int
    {
        if ($machineCenterId) {
            return 1;
        }

        return max(1, (int) floor((float) ($workCenter->capacity ?: 1)));
    }

    private function capacityAvailable(WorkCenter $workCenter, ?int $machineCenterId, CarbonInterface $start, CarbonInterface $finish, ?int $excludeScheduleId): bool
    {
        $query = ProductionOperationSchedule::query()
            ->where('scheduled_start_at', '<', $finish)
            ->where('scheduled_finish_at', '>', $start)
            ->whereNotIn('status', ['cancelled', 'rescheduled']);

        if ($excludeScheduleId) {
            $query->where('production_schedule_id', '!=', $excludeScheduleId);
        }

        if ($machineCenterId) {
            return ! (clone $query)->where('machine_center_id', $machineCenterId)->exists();
        }

        $overlappingCount = (clone $query)
            ->whereNull('machine_center_id')
            ->where('work_center_id', $workCenter->id)
            ->count();

        return $overlappingCount < $this->concurrentCapacity($workCenter, null);
    }

    private function nextConflictingFinish(int $workCenterId, ?int $machineCenterId, CarbonInterface $start, CarbonInterface $finish, ?int $excludeScheduleId): ?Carbon
    {
        return $this->conflictingSchedules($workCenterId, $machineCenterId, $start, $finish, $excludeScheduleId)
            ->max('scheduled_finish_at');
    }

    private function previousConflictingStart(int $workCenterId, ?int $machineCenterId, CarbonInterface $start, CarbonInterface $finish, ?int $excludeScheduleId): ?Carbon
    {
        return $this->conflictingSchedules($workCenterId, $machineCenterId, $start, $finish, $excludeScheduleId)
            ->min('scheduled_start_at');
    }

    /**
     * @return Collection<int, ProductionOperationSchedule>
     */
    private function conflictingSchedules(int $workCenterId, ?int $machineCenterId, CarbonInterface $start, CarbonInterface $finish, ?int $excludeScheduleId): Collection
    {
        $query = ProductionOperationSchedule::query()
            ->where('scheduled_start_at', '<', $finish)
            ->where('scheduled_finish_at', '>', $start)
            ->whereNotIn('status', ['cancelled', 'rescheduled']);

        if ($excludeScheduleId) {
            $query->where('production_schedule_id', '!=', $excludeScheduleId);
        }

        if ($machineCenterId) {
            $query->where('machine_center_id', $machineCenterId);
        } else {
            $query->whereNull('machine_center_id')->where('work_center_id', $workCenterId);
        }

        return $query->get();
    }

    private function shiftStart(WorkCenterCalendar $calendar): Carbon
    {
        return Carbon::parse($calendar->date->toDateString().' '.Carbon::parse($calendar->start_time)->format('H:i:s'));
    }

    private function shiftEnd(WorkCenterCalendar $calendar): Carbon
    {
        return Carbon::parse($calendar->date->toDateString().' '.Carbon::parse($calendar->end_time)->format('H:i:s'));
    }
}
