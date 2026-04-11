<?php

namespace App\Services;

use App\Enums\PayCodeType;
use App\Enums\PayrollStatus;
use App\Enums\SourceType;
use App\Models\Employee;
use App\Models\GlEntry;
use App\Models\PayrollDocument;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollPostingService
{
    /**
     * Post a Payroll Document to the General Ledger.
     */
    public function post(PayrollDocument $document): void
    {
        if ($document->status === PayrollStatus::POSTED) {
            throw new Exception("Payroll document {$document->document_number} is already posted.");
        }

        DB::transaction(function () use ($document) {
            $documentNumber = $document->document_number;
            $postingDate = $document->period_end; // Standard practice uses period end
            $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;

            foreach ($document->lines as $line) {
                $employee = $line->employee;
                $payCode = $line->payCode;
                $amount = $line->amount;

                if ($amount <= 0) {
                    continue;
                }

                $postingGroup = $employee->employeePostingGroup;
                if (! $postingGroup) {
                    throw new Exception("Employee {$employee->employee_number} does not have an active posting group.");
                }

                $payablesAccountId = $postingGroup->payables_account_id;
                $payCodeAccountId = $payCode->gl_account_id;

                if (! $payablesAccountId || ! $payCodeAccountId) {
                    throw new Exception("Missing GL configuration for Payroll Line ID: {$line->id}");
                }

                $description = "Payroll {$payCode->name} - {$employee->first_name} {$employee->last_name}";

                if ($payCode->type === PayCodeType::EARNING) {
                    // Dr Expense/Cost, Cr Payable
                    $this->createGlEntry($payCodeAccountId, $amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->createGlEntry($payablesAccountId, -$amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                } else {
                    // Deduction
                    // Dr Payable, Cr Tax/Deduction Liability
                    $this->createGlEntry($payablesAccountId, $amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->createGlEntry($payCodeAccountId, -$amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                }
            }

            $document->status = PayrollStatus::POSTED;
            $document->save();
        });
    }

    private function createGlEntry(int $accountId, float $amount, $postingDate, string $docNo, string $desc, int $transactionNumber, Employee $employee): void
    {
        $debit = $amount > 0 ? $amount : 0;
        $credit = $amount < 0 ? abs($amount) : 0;

        GlEntry::create([
            'chart_of_account_id' => $accountId,
            'transaction_number' => $transactionNumber,
            'source_type' => SourceType::EMPLOYEE,
            'source_number' => $employee->employee_number,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => 'PAYROLL',
            'document_number' => $docNo,
            'description' => $desc,
            'amount' => $amount,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'user_id' => Auth::id() ?? User::first()->id ?? 1,
        ]);
    }
}
