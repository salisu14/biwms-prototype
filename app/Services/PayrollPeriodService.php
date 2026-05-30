<?php

namespace App\Services;

use App\Enums\PayCodeType;
use App\Enums\PayrollPeriodStatus;
use App\Models\EmployeeYtdBalance;
use App\Models\PayrollPeriod;
use Exception;
use Illuminate\Support\Facades\DB;

class PayrollPeriodService
{
    /**
     * Close a payroll period and finalize YTD balances.
     */
    public function close(PayrollPeriod $period): void
    {
        if ($period->status === PayrollPeriodStatus::CLOSED) {
            throw new Exception('Period is already closed.');
        }

        DB::transaction(function () use ($period) {
            $documents = $period->documents()->with(['lines.payCode'])->get();
            if ($documents->isEmpty()) {
                throw new Exception('Cannot close period. No payroll document exists for this period.');
            }

            $hasOpenDocuments = $documents->contains(fn ($document) => $document->status?->value !== 'POSTED');
            if ($hasOpenDocuments) {
                throw new Exception('Cannot close period. All payroll documents for this period must be posted first.');
            }

            $allLines = $documents->flatMap(fn ($document) => $document->lines);

            foreach ($allLines->groupBy('employee_id') as $employeeId => $lines) {
                $year = $period->start_date->year;

                $ytd = EmployeeYtdBalance::firstOrCreate(
                    ['employee_id' => $employeeId, 'year' => $year],
                    ['gross_earnings' => 0, 'tax_deducted' => 0, 'social_security_employee' => 0, 'social_security_employer' => 0, 'net_paid' => 0]
                );

                $gross = (float) $lines
                    ->filter(fn ($line) => strtoupper((string) $line->line_type) === strtoupper(PayCodeType::EARNING->getLabel()))
                    ->sum('amount');
                $tax = (float) $lines
                    ->filter(fn ($line) => strtoupper((string) ($line->payCode?->code ?? '')) === 'PAYE')
                    ->sum('amount');
                $ss = (float) $lines
                    ->filter(fn ($line) => in_array(strtoupper((string) ($line->payCode?->code ?? '')), ['NSSF', 'NHIF', 'SHIF'], true))
                    ->sum('amount');
                $totalDeductions = (float) $lines
                    ->filter(fn ($line) => strtoupper((string) $line->line_type) === strtoupper(PayCodeType::DEDUCTION->getLabel()))
                    ->sum('amount');
                $net = $gross - $totalDeductions;

                $ytd->increment('gross_earnings', $gross);
                $ytd->increment('tax_deducted', $tax);
                $ytd->increment('social_security_employee', $ss);
                $ytd->increment('net_paid', $net);
            }

            $period->status = PayrollPeriodStatus::CLOSED;
            $period->is_current = false;
            $period->save();
        });
    }
}
