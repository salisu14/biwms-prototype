<?php

namespace App\Services;

use App\Models\PayrollDocument;
use App\Models\EmployeeBankAccount;
use Exception;

class PayrollPaymentService
{
    /**
     * Generate a CSV bank payment file for a payroll document.
     */
    public function generateBankFile(PayrollDocument $document): string
    {
        $headers = ['Employee No', 'Name', 'Bank Code', 'Bank Name', 'Account Number', 'Net Amount', 'Payment Method'];
        $rows = [];
        $rows[] = implode(',', $headers);

        foreach ($document->lines()->where('line_type', 'NET')->orWhere('amount', '>', 0)->get()->groupBy('employee_id') as $employeeId => $lines) {
            $employee = $lines->first()->employee;
            $netAmount = $document->lines()->where('employee_id', $employeeId)->sum('amount'); // This is simplified, ideally we have a NET line type

            // In actual implementation, we'd sum Earnings - Deductions
            $earnings = $document->lines()->where('employee_id', $employeeId)->where('line_type', 'EARNING')->sum('amount');
            $deductions = $document->lines()->where('employee_id', $employeeId)->where('line_type', 'DEDUCTION')->sum('amount');
            $actualNet = $earnings - $deductions;

            if ($actualNet <= 0) continue;

            $bankAccount = $employee->bankAccounts()->where('is_primary', true)->first();
            
            $rows[] = implode(',', [
                $employee->employee_number,
                "{$employee->first_name} {$employee->last_name}",
                $bankAccount?->bank_code ?? 'N/A',
                $bankAccount?->bank_name ?? 'N/A',
                $bankAccount?->account_number ?? 'N/A',
                round($actualNet, 2),
                $bankAccount?->payment_method ?? 'Bank Transfer'
            ]);
        }

        return implode("\n", $rows);
    }
}
