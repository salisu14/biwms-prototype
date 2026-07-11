<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Enums\PayCodeType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\AttendancePayrollReviewBatch;
use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\AttendancePayrollRule;
use App\Models\AttendanceReviewItem;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class AttendancePayrollPostingService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @return array{posted_lines: int, skipped_lines: int}
     */
    public function post(AttendancePayrollReviewBatch $batch, PayrollDocument $payrollDocument, User $user): array
    {
        return DB::transaction(function () use ($batch, $payrollDocument, $user): array {
            $lockedBatch = AttendancePayrollReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $lockedDocument = PayrollDocument::query()->lockForUpdate()->findOrFail($payrollDocument->id);

            if ($lockedBatch->status !== AttendancePayrollReviewBatch::STATUS_APPROVED) {
                throw new \RuntimeException('Only approved attendance payroll review batches can be posted.');
            }

            if ($lockedDocument->status instanceof PayrollStatus && ! $lockedDocument->status->canEdit()) {
                throw new \RuntimeException('Attendance payroll adjustments can only be posted to editable payroll documents.');
            }

            if ($lockedDocument->period?->status instanceof PayrollPeriodStatus && $lockedDocument->period->status !== PayrollPeriodStatus::OPEN) {
                throw new \RuntimeException('Attendance payroll adjustments can only be posted to an open payroll period.');
            }

            $postedLines = 0;
            $skippedLines = 0;

            $lines = $lockedBatch->lines()
                ->with('rule')
                ->whereIn('status', [AttendancePayrollReviewBatchLine::STATUS_APPROVED, AttendancePayrollReviewBatchLine::STATUS_PENDING])
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                if ($line->payroll_adjustment_reference) {
                    $skippedLines++;

                    continue;
                }

                $payrollLine = $this->createPayrollLine($line, $lockedDocument);
                $line->forceFill([
                    'status' => AttendancePayrollReviewBatchLine::STATUS_POSTED,
                    'payroll_adjustment_reference' => $payrollLine->id,
                    'metadata' => [
                        ...($line->metadata ?? []),
                        'payroll_document_id' => $lockedDocument->id,
                        'posted_by' => $user->id,
                        'posted_at' => now()->toIso8601String(),
                    ],
                ])->save();

                $postedLines++;
            }

            if ($postedLines === 0 && $skippedLines > 0) {
                throw new \RuntimeException('Attendance payroll review batch has already been posted.');
            }

            $lockedBatch->forceFill([
                'status' => $postedLines > 0 && $skippedLines > 0
                    ? AttendancePayrollReviewBatch::STATUS_PARTIALLY_POSTED
                    : AttendancePayrollReviewBatch::STATUS_POSTED,
                'posted_by' => $user->id,
                'posted_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric(
                eventType: 'attendance_payroll',
                action: 'batch_posted_to_payroll',
                auditable: $lockedBatch,
                userId: $user->id,
                metadata: [
                    'payroll_document_id' => $lockedDocument->id,
                    'posted_lines' => $postedLines,
                    'skipped_lines' => $skippedLines,
                ],
            );

            return [
                'posted_lines' => $postedLines,
                'skipped_lines' => $skippedLines,
            ];
        });
    }

    public function reverseLine(AttendancePayrollReviewBatchLine $line, PayrollDocument $payrollDocument, User $user, string $reason): PayrollLine
    {
        if (blank($reason)) {
            throw new \RuntimeException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($line, $payrollDocument, $user, $reason): PayrollLine {
            $lockedLine = AttendancePayrollReviewBatchLine::query()->with('rule')->lockForUpdate()->findOrFail($line->id);
            $lockedDocument = PayrollDocument::query()->lockForUpdate()->findOrFail($payrollDocument->id);

            if ($lockedLine->status !== AttendancePayrollReviewBatchLine::STATUS_POSTED || ! $lockedLine->payroll_adjustment_reference) {
                throw new \RuntimeException('Only posted attendance payroll adjustment lines can be reversed.');
            }

            $original = PayrollLine::query()->findOrFail((int) $lockedLine->payroll_adjustment_reference);
            $reversal = PayrollLine::query()->create([
                'payroll_document_id' => $lockedDocument->id,
                'employee_id' => $lockedLine->employee_id,
                'pay_code_id' => $original->pay_code_id,
                'line_type' => $original->line_type,
                'amount' => -1 * (float) $original->amount,
                'hours' => $original->hours,
                'rate' => $original->rate,
                'description' => 'Reversal: '.$reason,
                'attendance_payroll_review_batch_line_id' => $lockedLine->id,
            ]);

            $lockedLine->forceFill([
                'status' => AttendancePayrollReviewBatchLine::STATUS_REVERSED,
                'metadata' => [
                    ...($lockedLine->metadata ?? []),
                    'reversal_payroll_line_id' => $reversal->id,
                    'reversal_reason' => $reason,
                    'reversed_by' => $user->id,
                    'reversed_at' => now()->toIso8601String(),
                ],
            ])->save();

            $this->auditTrailService->recordGeneric('attendance_payroll', 'line_reversed', $lockedLine, userId: $user->id, metadata: ['reason' => $reason]);

            return $reversal;
        });
    }

    private function createPayrollLine(AttendancePayrollReviewBatchLine $line, PayrollDocument $payrollDocument): PayrollLine
    {
        $rule = $line->rule;
        if (! $rule instanceof AttendancePayrollRule) {
            throw new \RuntimeException('Attendance payroll adjustment line is missing a payroll rule.');
        }

        $payCodeId = $this->payCodeIdForLine($line, $rule);
        if (! $payCodeId) {
            throw new \RuntimeException("Attendance payroll rule {$rule->name} does not define the required pay code.");
        }

        $amount = (float) ($line->approved_amount ?? $line->suggested_amount ?? 0);
        if ($amount <= 0) {
            throw new \RuntimeException('Attendance payroll adjustment amount must be positive before posting.');
        }

        return PayrollLine::query()->create([
            'payroll_document_id' => $payrollDocument->id,
            'employee_id' => $line->employee_id,
            'pay_code_id' => $payCodeId,
            'line_type' => $this->lineType($line, $rule),
            'amount' => $amount,
            'hours' => round(((int) $line->quantity_minutes) / 60, 2),
            'rate' => $line->rate,
            'description' => $this->description($line),
            'attendance_payroll_review_batch_line_id' => $line->id,
        ]);
    }

    private function payCodeIdForLine(AttendancePayrollReviewBatchLine $line, AttendancePayrollRule $rule): ?int
    {
        if ($line->line_type === AttendanceReviewItem::ISSUE_APPROVED_OVERTIME) {
            return $rule->earning_component_id;
        }

        return $rule->deduction_component_id;
    }

    private function lineType(AttendancePayrollReviewBatchLine $line, AttendancePayrollRule $rule): string
    {
        if ($line->line_type === AttendanceReviewItem::ISSUE_APPROVED_OVERTIME || $rule->impact_type === AttendancePayrollRule::IMPACT_EARNING) {
            return PayCodeType::EARNING->getLabel();
        }

        if ($rule->impact_type === AttendancePayrollRule::IMPACT_INFORMATIONAL) {
            return PayCodeType::BENEFIT->getLabel() ?? 'Benefit';
        }

        return PayCodeType::DEDUCTION->getLabel();
    }

    private function description(AttendancePayrollReviewBatchLine $line): string
    {
        return match ($line->line_type) {
            AttendanceReviewItem::ISSUE_APPROVED_OVERTIME => 'Approved attendance overtime adjustment',
            AttendanceReviewItem::ISSUE_UNPAID_ABSENCE => 'Approved unpaid attendance absence adjustment',
            default => 'Attendance payroll adjustment',
        };
    }
}
