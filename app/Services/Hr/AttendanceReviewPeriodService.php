<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\EmployeeAttendanceDay;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class AttendanceReviewPeriodService
{
    public function __construct(
        private readonly AttendanceExceptionReviewService $exceptionReviewService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): AttendanceReviewPeriod
    {
        return DB::transaction(function () use ($data, $user): AttendanceReviewPeriod {
            $this->assertNoOverlap($data['date_from'], $data['date_to'], $data['business_id'] ?? null);

            $period = AttendanceReviewPeriod::query()->create([
                ...$data,
                'name' => $data['name'] ?? $data['code'],
                'status' => $data['status'] ?? AttendanceReviewPeriod::STATUS_OPEN,
                'opened_by' => $user->id,
                'opened_at' => now(),
            ]);

            $this->auditTrailService->recordGeneric('attendance_review', 'period_created', $period, userId: $user->id);

            return $period;
        });
    }

    public function submit(AttendanceReviewPeriod $period, User $user): AttendanceReviewPeriod
    {
        return DB::transaction(function () use ($period, $user): AttendanceReviewPeriod {
            $locked = AttendanceReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->exceptionReviewService->generateForPeriod($locked);
            $locked->forceFill([
                'status' => AttendanceReviewPeriod::STATUS_UNDER_REVIEW,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_review', 'period_submitted', $locked, userId: $user->id);

            return $locked->fresh();
        });
    }

    public function approve(AttendanceReviewPeriod $period, User $user): AttendanceReviewPeriod
    {
        return DB::transaction(function () use ($period, $user): AttendanceReviewPeriod {
            $locked = AttendanceReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $this->assertNoBlockingItems($locked);
            $locked->forceFill([
                'status' => AttendanceReviewPeriod::STATUS_APPROVED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_review', 'period_approved', $locked, userId: $user->id);

            return $locked->fresh();
        });
    }

    public function lock(AttendanceReviewPeriod $period, User $user): AttendanceReviewPeriod
    {
        return DB::transaction(function () use ($period, $user): AttendanceReviewPeriod {
            $locked = AttendanceReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! in_array($locked->status, [AttendanceReviewPeriod::STATUS_APPROVED, AttendanceReviewPeriod::STATUS_REOPENED], true)) {
                throw new \RuntimeException('Only approved or reopened attendance review periods can be locked.');
            }

            EmployeeAttendanceDay::query()
                ->whereBetween('attendance_date', [$locked->date_from, $locked->date_to])
                ->get()
                ->each(function (EmployeeAttendanceDay $day) use ($locked): void {
                    $day->forceFill([
                        'locked_by_review_period_id' => $locked->id,
                        'locked_at' => now(),
                        'locked_snapshot_hash' => $this->snapshotHash($day),
                    ])->save();
                });

            $locked->forceFill([
                'status' => AttendanceReviewPeriod::STATUS_LOCKED,
                'locked_by' => $user->id,
                'locked_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_review', 'period_locked', $locked, userId: $user->id);

            return $locked->fresh();
        });
    }

    public function reopen(AttendanceReviewPeriod $period, User $user, string $reason): AttendanceReviewPeriod
    {
        if (blank($reason)) {
            throw new \RuntimeException('A reopen reason is required.');
        }

        return DB::transaction(function () use ($period, $user, $reason): AttendanceReviewPeriod {
            $locked = AttendanceReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! in_array($locked->status, [AttendanceReviewPeriod::STATUS_LOCKED, AttendanceReviewPeriod::STATUS_EXPORTED], true)) {
                throw new \RuntimeException('Only locked or exported attendance review periods can be reopened.');
            }

            EmployeeAttendanceDay::query()
                ->where('locked_by_review_period_id', $locked->id)
                ->update([
                    'locked_by_review_period_id' => null,
                    'locked_at' => null,
                    'locked_snapshot_hash' => null,
                ]);

            $locked->forceFill([
                'status' => AttendanceReviewPeriod::STATUS_REOPENED,
                'reopened_by' => $user->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ])->save();

            $locked->payrollBatches()
                ->whereIn('status', ['draft', 'pending_review', 'approved'])
                ->update(['status' => 'cancelled', 'notes' => 'Cancelled because attendance review period was reopened.']);

            $this->auditTrailService->recordGeneric('attendance_review', 'period_reopened', $locked, userId: $user->id, metadata: ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    private function assertNoOverlap(string $dateFrom, string $dateTo, ?int $businessId, ?int $ignoreId = null): void
    {
        $overlap = AttendanceReviewPeriod::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('business_id', $businessId)
            ->whereDate('date_from', '<=', $dateTo)
            ->whereDate('date_to', '>=', $dateFrom)
            ->exists();

        if ($overlap) {
            throw new \RuntimeException('Attendance review period date range overlaps an existing period.');
        }
    }

    private function assertNoBlockingItems(AttendanceReviewPeriod $period): void
    {
        $blockingExists = $period->items()
            ->whereIn('severity', ['critical'])
            ->whereNotIn('review_status', [AttendanceReviewItem::STATUS_RESOLVED, AttendanceReviewItem::STATUS_WAIVED])
            ->exists();

        if ($blockingExists) {
            throw new \RuntimeException('Attendance review period has unresolved blocking exceptions.');
        }
    }

    private function snapshotHash(EmployeeAttendanceDay $day): string
    {
        return hash('sha256', json_encode($day->only([
            'status', 'worked_minutes', 'late_minutes', 'early_departure_minutes',
            'overtime_minutes', 'missing_clock_out', 'payroll_review_required',
        ]), JSON_THROW_ON_ERROR));
    }
}
