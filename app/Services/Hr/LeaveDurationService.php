<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\LeaveHoliday;
use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Support\Carbon;

class LeaveDurationService
{
    /**
     * @param  array<int, int>  $weekendDays
     */
    public function calculate(
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        string $startPart = 'full_day',
        string $endPart = 'full_day',
        ?int $businessId = null,
        array $weekendDays = [Carbon::SATURDAY, Carbon::SUNDAY],
    ): float {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->startOfDay();

        if ($start->greaterThan($end)) {
            throw new Exception('Leave start date cannot be after the end date.');
        }

        $quantity = 0.0;
        $holidays = $this->holidayMap($start, $end, $businessId);

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (in_array($date->dayOfWeek, $weekendDays, true) || isset($holidays[$date->toDateString()])) {
                continue;
            }

            $quantity += 1.0;
        }

        if ($quantity <= 0) {
            return 0.0;
        }

        if ($start->isSameDay($end)) {
            return $this->singleDayQuantity($startPart, $endPart, $quantity);
        }

        if ($startPart !== 'full_day') {
            $quantity -= 0.5;
        }

        if ($endPart !== 'full_day') {
            $quantity -= 0.5;
        }

        return max(0.0, round($quantity, 2));
    }

    public function assertNoOverlap(LeaveRequest $request): void
    {
        $overlapExists = LeaveRequest::query()
            ->where('employee_id', $request->employee_id)
            ->whereKeyNot($request->id)
            ->whereIn('status', [
                LeaveRequest::STATUS_SUBMITTED,
                LeaveRequest::STATUS_MANAGER_APPROVED,
                LeaveRequest::STATUS_APPROVED,
                LeaveRequest::STATUS_POSTED,
            ])
            ->whereDate('start_date', '<=', $request->end_date)
            ->whereDate('end_date', '>=', $request->start_date)
            ->exists();

        if ($overlapExists) {
            throw new Exception('This leave request overlaps another submitted or approved leave request.');
        }
    }

    /**
     * @return array<string, true>
     */
    private function holidayMap(Carbon $start, Carbon $end, ?int $businessId): array
    {
        return LeaveHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($query) use ($businessId): void {
                $query->whereNull('business_id');

                if ($businessId !== null) {
                    $query->orWhere('business_id', $businessId);
                }
            })
            ->pluck('holiday_date')
            ->mapWithKeys(fn (mixed $date): array => [Carbon::parse($date)->toDateString() => true])
            ->all();
    }

    private function singleDayQuantity(string $startPart, string $endPart, float $baseQuantity): float
    {
        if ($baseQuantity <= 0) {
            return 0.0;
        }

        if ($startPart === 'full_day' && $endPart === 'full_day') {
            return 1.0;
        }

        return 0.5;
    }
}
