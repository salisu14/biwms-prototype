<?php

namespace App\Services;

use App\Enums\PayCodeType;
use App\Enums\PayrollStatus;
use App\Enums\SourceType;
use App\Events\PayrollPosted;
use App\Models\Employee;
use App\Models\GlEntry;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PayrollPostingService
{
    public function __construct(
        private readonly PostingDateValidator $postingDateValidator
    ) {}

    /**
     * Post a Payroll Document to the General Ledger.
     */
    public function post(PayrollDocument $document): void
    {
        Gate::authorize('post', $document);

        if ($document->status === PayrollStatus::POSTED) {
            throw new Exception("Payroll document {$document->document_number} is already posted.");
        }

        if (! in_array($document->status, [PayrollStatus::CALCULATED, PayrollStatus::APPROVED], true)) {
            throw new Exception("Payroll document {$document->document_number} must be calculated or approved before posting.");
        }

        $this->postingDateValidator->validate($document->period_end);

        DB::transaction(function () use ($document) {
            $document->loadMissing(['lines.employee.payrollPostingGroup', 'lines.payCode']);

            if ($document->lines->isEmpty()) {
                throw new Exception("Payroll document {$document->document_number} has no lines to post.");
            }

            $documentNumber = $document->document_number;
            $postingDate = $document->period_end;
            $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;
            $entryNumber = (GlEntry::max('entry_number') ?? 0) + 1;

            foreach ($document->lines as $line) {
                $employee = $line->employee;
                $payCode = $line->payCode;
                $amount = (float) $line->amount;

                if ($amount <= 0) {
                    continue;
                }

                $postingGroup = $employee->payrollPostingGroup;
                if (! $postingGroup) {
                    throw new Exception("Employee {$employee->employee_number} does not have an active payroll posting group.");
                }

                $netPayAccount = $postingGroup->net_pay_account_id;
                $payCodeAccountId = $payCode->gl_account_id;

                $description = "Payroll {$payCode->name} - {$employee->first_name} {$employee->last_name}";

                if ($payCode->type === PayCodeType::EARNING) {
                    // Dr Salaries/Wages (Expense), Cr Net Pay (Liability)
                    $expenseAccount = $payCodeAccountId ?? $postingGroup->salaries_account_id;
                    $glEntry = $this->createGlEntry($entryNumber++, $expenseAccount, $amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->createGlEntry($entryNumber++, $netPayAccount, -$amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->markLinePosted($line, $glEntry);
                } elseif ($payCode->type === PayCodeType::DEDUCTION) {
                    // Dr Net Pay (Liability), Cr Tax/Deduction Liability
                    $liabilityAccount = $payCodeAccountId;

                    // Priority fallback to posting group standard accounts
                    if ($payCode->is_statutory) {
                        if ($payCode->code === 'PAYE') {
                            $liabilityAccount = $postingGroup->tax_payable_account_id;
                        } else {
                            $liabilityAccount = $postingGroup->social_security_account_id;
                        }
                    }

                    if (! $liabilityAccount) {
                        throw new Exception("Missing liability account for deduction: {$payCode->name}");
                    }

                    $glEntry = $this->createGlEntry($entryNumber++, $netPayAccount, $amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->createGlEntry($entryNumber++, $liabilityAccount, -$amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->markLinePosted($line, $glEntry);
                } elseif ($payCode->type === PayCodeType::BENEFIT) {
                    // Employer Cost: Dr Expense, Cr Liability
                    $expenseAccount = $payCodeAccountId ?? $postingGroup->salaries_account_id;
                    $liabilityAccount = $postingGroup->social_security_account_id;

                    $glEntry = $this->createGlEntry($entryNumber++, $expenseAccount, $amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->createGlEntry($entryNumber++, $liabilityAccount, -$amount, $postingDate, $documentNumber, $description, $transactionNumber, $employee);
                    $this->markLinePosted($line, $glEntry);
                }
            }

            $entries = GlEntry::query()->where('document_type', 'PAYROLL')->where('document_number', $documentNumber)->get();

            if (round((float) $entries->sum('debit_amount'), 2) !== round((float) $entries->sum('credit_amount'), 2)) {
                throw new Exception("Payroll document {$documentNumber} produced unbalanced G/L entries.");
            }

            $document->status = PayrollStatus::POSTED;
            $document->save();
        });

        PayrollPosted::dispatch($document->fresh());
    }

    private function createGlEntry(int $entryNumber, int $accountId, float $amount, $postingDate, string $docNo, string $desc, int $transactionNumber, Employee $employee): GlEntry
    {
        $debit = $amount > 0 ? $amount : 0;
        $credit = $amount < 0 ? abs($amount) : 0;

        return GlEntry::create([
            'entry_number' => $entryNumber,
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
            'user_id' => Auth::id() ?? 1,
        ]);
    }

    private function markLinePosted(PayrollLine $line, GlEntry $glEntry): void
    {
        $line->forceFill([
            'posted_to_g_l' => true,
            'posted_at' => now(),
            'gl_entry_id' => $glEntry->id,
        ])->save();
    }
}
