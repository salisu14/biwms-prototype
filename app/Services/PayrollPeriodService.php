<?php

namespace App\Services;

use App\Models\PayrollPeriod;
use App\Models\PayrollDocument;
use App\Models\EmployeeYtdBalance;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayCodeType;
use Illuminate\Support\Facades\DB;
use Exception;

class PayrollPeriodService
{
    /**
     * Close a payroll period and finalize YTD balances.
     */
    public function close(PayrollPeriod $period): void
    {
        if ($period->status === PayrollPeriodStatus::Closed) {
            throw new Exception("Period is already closed.");
        }

        DB::transaction(function () use ($period) {
            // 1. Ensure document is posted
            $document = $period->documents()->first();
            if (!$document || $document->status->value !== 'POSTED') {
                throw new Exception("Cannot close period. The payroll document must be posted first.");
            }

            // 2. Update YTD Balances
            foreach ($document->lines()->get()->groupBy('employee_id') as $employeeId => $lines) {
                $year = $period->start_date->year;
                
                $ytd = EmployeeYtdBalance::firstOrCreate(
                    ['employee_id' => $employeeId, 'year' => $year],
                    ['gross_earnings' => 0, 'tax_deducted' => 0, 'social_security_employee' => 0, 'social_security_employer' => 0, 'net_paid' => 0]
                );

                $gross = $lines->where('line_type', PayCodeType::EARNING)->sum('amount');
                $tax = $lines->where('payCode.code', 'PAYE')->sum('amount');
                $ss = $lines->where('payCode.code', 'NSSF')->sum('amount');
                $net = $gross - $lines->where('line_type', PayCodeType::DEDUCTION)->sum('amount');

                $ytd->increment('gross_earnings', $gross);
                $ytd->increment('tax_deducted', $tax);
                $ytd->increment('social_security_employee', $ss);
                $ytd->increment('net_paid', $net);
            }

            // 3. Mark Period as Closed
            $period->status = PayrollPeriodStatus::Closed;
            $period->is_current = false;
            $period->save();
        });
    }
}
