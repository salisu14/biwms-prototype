<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\AttendancePayrollReviewBatch;
use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\AttendancePayrollRule;
use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendancePayrollReviewBatchService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function generate(AttendanceReviewPeriod $period, PayrollPeriod $payrollPeriod, User $user): AttendancePayrollReviewBatch
    {
        return DB::transaction(function () use ($period, $payrollPeriod, $user): AttendancePayrollReviewBatch {
            $lockedPeriod = AttendanceReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if (! in_array($lockedPeriod->status, [AttendanceReviewPeriod::STATUS_APPROVED, AttendanceReviewPeriod::STATUS_LOCKED], true)) {
                throw new \RuntimeException('Attendance payroll review batches can only be generated from approved or locked attendance review periods.');
            }

            $batch = AttendancePayrollReviewBatch::query()
                ->where('attendance_review_period_id', $lockedPeriod->id)
                ->where('payroll_period_id', $payrollPeriod->id)
                ->whereNotIn('status', [AttendancePayrollReviewBatch::STATUS_CANCELLED, AttendancePayrollReviewBatch::STATUS_REVERSED])
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                $batch = AttendancePayrollReviewBatch::query()->create([
                    'attendance_review_period_id' => $lockedPeriod->id,
                    'payroll_period_id' => $payrollPeriod->id,
                    'batch_number' => $this->batchNumber($lockedPeriod),
                    'status' => AttendancePayrollReviewBatch::STATUS_DRAFT,
                    'generated_by' => $user->id,
                    'generated_at' => now(),
                ]);
            }

            if (in_array($batch->status, [AttendancePayrollReviewBatch::STATUS_APPROVED, AttendancePayrollReviewBatch::STATUS_POSTED, AttendancePayrollReviewBatch::STATUS_PARTIALLY_POSTED], true)) {
                throw new \RuntimeException('Approved or posted attendance payroll review batches cannot be regenerated.');
            }

            $linePayloads = $this->buildLines($lockedPeriod);
            $currentKeys = [];

            foreach ($linePayloads as $payload) {
                $line = AttendancePayrollReviewBatchLine::query()->updateOrCreate(
                    [
                        'attendance_payroll_review_batch_id' => $batch->id,
                        'employee_id' => $payload['employee_id'],
                        'attendance_review_item_id' => $payload['attendance_review_item_id'],
                        'line_type' => $payload['line_type'],
                    ],
                    $payload + ['status' => AttendancePayrollReviewBatchLine::STATUS_PENDING],
                );

                $currentKeys[] = $line->id;
            }

            $batch->lines()
                ->where('status', AttendancePayrollReviewBatchLine::STATUS_PENDING)
                ->when($currentKeys !== [], fn ($query) => $query->whereNotIn('id', $currentKeys))
                ->delete();

            $batch->forceFill([
                'total_overtime_minutes' => (int) $batch->lines()->where('line_type', AttendanceReviewItem::ISSUE_APPROVED_OVERTIME)->sum('quantity_minutes'),
                'total_unpaid_minutes' => (int) $batch->lines()->where('line_type', AttendanceReviewItem::ISSUE_UNPAID_ABSENCE)->sum('quantity_minutes'),
                'total_suggested_amount' => (float) $batch->lines()->sum('suggested_amount'),
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_payroll', 'batch_generated', $batch, userId: $user->id);

            return $batch->fresh(['lines']);
        });
    }

    public function submit(AttendancePayrollReviewBatch $batch, User $user): AttendancePayrollReviewBatch
    {
        return $this->transition($batch, AttendancePayrollReviewBatch::STATUS_PENDING_REVIEW, $user, 'batch_submitted');
    }

    public function approve(AttendancePayrollReviewBatch $batch, User $user): AttendancePayrollReviewBatch
    {
        return DB::transaction(function () use ($batch, $user): AttendancePayrollReviewBatch {
            $locked = AttendancePayrollReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);

            if (! in_array($locked->status, [AttendancePayrollReviewBatch::STATUS_DRAFT, AttendancePayrollReviewBatch::STATUS_PENDING_REVIEW], true)) {
                throw new \RuntimeException('Only draft or pending attendance payroll batches can be approved.');
            }

            $locked->lines()
                ->where('status', AttendancePayrollReviewBatchLine::STATUS_PENDING)
                ->update([
                    'status' => AttendancePayrollReviewBatchLine::STATUS_APPROVED,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

            $locked->forceFill([
                'status' => AttendancePayrollReviewBatch::STATUS_APPROVED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'total_approved_amount' => (float) $locked->lines()->get()->sum(fn (AttendancePayrollReviewBatchLine $line): float => (float) ($line->approved_amount ?? $line->suggested_amount ?? 0)),
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_payroll', 'batch_approved', $locked, userId: $user->id);

            return $locked->fresh(['lines']);
        });
    }

    public function reject(AttendancePayrollReviewBatch $batch, User $user, string $reason): AttendancePayrollReviewBatch
    {
        if (blank($reason)) {
            throw new \RuntimeException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($batch, $user, $reason): AttendancePayrollReviewBatch {
            $locked = AttendancePayrollReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $locked->forceFill([
                'status' => AttendancePayrollReviewBatch::STATUS_REJECTED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'notes' => $reason,
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_payroll', 'batch_rejected', $locked, userId: $user->id, metadata: ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    public function overrideLineAmount(AttendancePayrollReviewBatchLine $line, float $amount, User $user, string $reason): AttendancePayrollReviewBatchLine
    {
        if (! $user->can('payroll.attendance_adjustment.override')) {
            throw new \RuntimeException('You are not authorized to override attendance payroll adjustments.');
        }

        if (blank($reason)) {
            throw new \RuntimeException('An override reason is required.');
        }

        return DB::transaction(function () use ($line, $amount, $user, $reason): AttendancePayrollReviewBatchLine {
            $locked = AttendancePayrollReviewBatchLine::query()->lockForUpdate()->findOrFail($line->id);

            if ($locked->status === AttendancePayrollReviewBatchLine::STATUS_POSTED) {
                throw new \RuntimeException('Posted attendance payroll adjustment lines cannot be overridden.');
            }

            $locked->forceFill([
                'approved_amount' => $amount,
                'status' => AttendancePayrollReviewBatchLine::STATUS_APPROVED,
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
                'metadata' => [
                    ...($locked->metadata ?? []),
                    'override_reason' => $reason,
                    'overridden_by' => $user->id,
                    'overridden_at' => now()->toIso8601String(),
                ],
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_payroll', 'line_overridden', $locked, userId: $user->id, metadata: ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    private function transition(AttendancePayrollReviewBatch $batch, string $status, User $user, string $auditAction): AttendancePayrollReviewBatch
    {
        return DB::transaction(function () use ($batch, $status, $user, $auditAction): AttendancePayrollReviewBatch {
            $locked = AttendancePayrollReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $locked->forceFill(['status' => $status])->save();

            $this->auditTrailService->recordGeneric('attendance_payroll', $auditAction, $locked, userId: $user->id);

            return $locked->fresh();
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(AttendanceReviewPeriod $period): array
    {
        return $period->items()
            ->with(['attendanceDay', 'employee'])
            ->whereIn('review_status', [AttendanceReviewItem::STATUS_RESOLVED, AttendanceReviewItem::STATUS_WAIVED])
            ->whereIn('issue_type', [AttendanceReviewItem::ISSUE_APPROVED_OVERTIME, AttendanceReviewItem::ISSUE_UNPAID_ABSENCE])
            ->get()
            ->map(function (AttendanceReviewItem $item): ?array {
                $rule = $this->ruleFor($item);
                if (! $rule) {
                    return null;
                }

                $minutes = $this->quantityMinutes($item);
                if ($minutes <= 0) {
                    return null;
                }

                $suggestedAmount = $this->calculateAmount($rule, $minutes);

                return [
                    'employee_attendance_day_id' => $item->employee_attendance_day_id,
                    'attendance_review_item_id' => $item->id,
                    'attendance_payroll_rule_id' => $rule->id,
                    'employee_id' => $item->employee_id,
                    'line_type' => $item->issue_type,
                    'quantity_minutes' => $minutes,
                    'quantity_days' => round($minutes / 480, 4),
                    'rate' => $rule->rate,
                    'suggested_amount' => $suggestedAmount,
                    'impact_type' => $rule->impact_type,
                    'notes' => $item->notes,
                    'metadata' => [
                        'review_item_source_hash' => $item->source_hash,
                        'attendance_date' => $item->attendanceDay?->attendance_date?->toDateString(),
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function ruleFor(AttendanceReviewItem $item): ?AttendancePayrollRule
    {
        return AttendancePayrollRule::query()
            ->where('is_active', true)
            ->where('attendance_issue_type', $item->issue_type)
            ->whereDate('effective_from', '<=', $item->attendanceDay?->attendance_date ?? now())
            ->where(function ($query) use ($item): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $item->attendanceDay?->attendance_date ?? now());
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    private function quantityMinutes(AttendanceReviewItem $item): int
    {
        $values = $item->original_values ?? [];

        return match ($item->issue_type) {
            AttendanceReviewItem::ISSUE_APPROVED_OVERTIME => (int) ($values['approved_overtime_minutes'] ?? 0),
            AttendanceReviewItem::ISSUE_UNPAID_ABSENCE => (int) ($values['unpaid_absence_minutes'] ?? $values['expected_minutes'] ?? 0),
            default => 0,
        };
    }

    private function calculateAmount(AttendancePayrollRule $rule, int $minutes): float
    {
        $rate = (float) ($rule->rate ?? 0);

        return match ($rule->calculation_method) {
            'hourly_rate' => round(($minutes / 60) * $rate, 4),
            'daily_rate' => round(($minutes / 480) * $rate, 4),
            'fixed_amount' => round($rate, 4),
            default => 0.0,
        };
    }

    private function batchNumber(AttendanceReviewPeriod $period): string
    {
        return 'ATB-'.$period->date_from?->format('Ym').'-'.Str::upper(Str::random(6));
    }
}
