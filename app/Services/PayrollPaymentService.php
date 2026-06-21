<?php

namespace App\Services;

use App\Enums\BankAccountLedgerEntryType;
use App\Enums\PayCodeType;
use App\Enums\PayrollStatus;
use App\Enums\SourceType;
use App\Events\PayrollSalaryPaid;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\GlEntry;
use App\Models\PayrollDocument;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PayrollPaymentService
{
    public function __construct(
        private readonly BankAccountLedgerService $bankAccountLedgerService,
        private readonly PostingDateValidator $postingDateValidator
    ) {}

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

            if ($actualNet <= 0) {
                continue;
            }

            $bankAccount = $employee->bankAccounts()->where('is_primary', true)->first();

            $rows[] = implode(',', [
                $employee->employee_number,
                "{$employee->first_name} {$employee->last_name}",
                $bankAccount?->bank_code ?? 'N/A',
                $bankAccount?->bank_name ?? 'N/A',
                $bankAccount?->account_number ?? 'N/A',
                round($actualNet, 2),
                $bankAccount?->payment_method ?? 'Bank Transfer',
            ]);
        }

        return implode("\n", $rows);
    }

    public function pay(PayrollDocument $document, BankAccount $bankAccount, int $userId): BankAccountLedgerEntry
    {
        Gate::forUser(User::query()->findOrFail($userId))->authorize('pay', $document);

        if ($document->status !== PayrollStatus::POSTED) {
            throw new Exception("Payroll document {$document->document_number} must be posted before salary payment.");
        }

        $this->postingDateValidator->validate($document->period_end);

        if (BankAccountLedgerEntry::query()
            ->where('document_type', 'payment')
            ->where('document_no', $this->paymentDocumentNo($document))
            ->exists()) {
            throw new Exception("Salary payment for payroll document {$document->document_number} is already posted.");
        }

        $employeesMissingPrimaryBank = $document->lines()
            ->with('employee.bankAccounts')
            ->get()
            ->pluck('employee')
            ->filter()
            ->unique('id')
            ->filter(fn ($employee) => ! $employee->bankAccounts()->where('is_primary', true)->exists())
            ->values();

        if ($employeesMissingPrimaryBank->isNotEmpty()) {
            $employeeList = $employeesMissingPrimaryBank
                ->map(fn ($employee) => "{$employee->employee_number} ({$employee->first_name} {$employee->last_name})")
                ->implode(', ');

            throw new Exception("Cannot pay payroll. The following employees have no primary bank account: {$employeeList}.");
        }

        $netPayAmount = $this->netPayAmount($document);

        if ($netPayAmount <= 0) {
            throw new Exception("Payroll document {$document->document_number} has no net pay to disburse.");
        }

        return DB::transaction(function () use ($document, $bankAccount, $userId, $netPayAmount) {
            $bankLedgerEntry = $this->bankAccountLedgerService->postPayment($bankAccount, [
                'amount' => $netPayAmount,
                'posting_date' => $document->period_end,
                'document_date' => $document->period_end,
                'document_no' => $this->paymentDocumentNo($document),
                'description' => "Salary payment {$document->document_number}",
                'entry_type' => BankAccountLedgerEntryType::WITHDRAWAL,
                'source_type' => 'payroll',
                'source_id' => $document->id,
                'source_no' => $document->document_number,
                'user_id' => $userId,
            ]);

            $this->createPaymentGlEntries($document, $bankAccount, $netPayAmount, $userId);

            PayrollSalaryPaid::dispatch($document->fresh(), $bankLedgerEntry->fresh());

            return $bankLedgerEntry->fresh();
        });
    }

    private function netPayAmount(PayrollDocument $document): float
    {
        $earnings = (float) $document->lines()->where('line_type', PayCodeType::EARNING->getLabel())->sum('amount');
        $deductions = (float) $document->lines()->where('line_type', PayCodeType::DEDUCTION->getLabel())->sum('amount');

        return $earnings - $deductions;
    }

    private function paymentDocumentNo(PayrollDocument $document): string
    {
        return "{$document->document_number}-PAY";
    }

    private function createPaymentGlEntries(PayrollDocument $document, BankAccount $bankAccount, float $amount, int $userId): void
    {
        $payrollPayableAccountId = $document->lines()
            ->with('employee.payrollPostingGroup')
            ->get()
            ->pluck('employee.payrollPostingGroup.net_pay_account_id')
            ->filter()
            ->unique()
            ->sole();

        if (! $bankAccount->gl_account_id) {
            throw new Exception("Bank account {$bankAccount->account_number} is missing a G/L account.");
        }

        $entryNumber = (int) (GlEntry::query()->max('entry_number') ?? 0) + 1;
        $transactionNumber = (int) (GlEntry::query()->max('transaction_number') ?? 0) + 1;
        $documentNo = $this->paymentDocumentNo($document);

        GlEntry::query()->create([
            'entry_number' => $entryNumber++,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $payrollPayableAccountId,
            'source_type' => SourceType::EMPLOYEE,
            'source_number' => $document->document_number,
            'posting_date' => $document->period_end,
            'document_date' => $document->period_end,
            'document_type' => 'PAYROLL_PAYMENT',
            'document_number' => $documentNo,
            'description' => "Clear payroll payable {$document->document_number}",
            'amount' => $amount,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'user_id' => $userId,
        ]);

        GlEntry::query()->create([
            'entry_number' => $entryNumber,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $bankAccount->gl_account_id,
            'source_type' => SourceType::BANK,
            'source_number' => $bankAccount->account_number,
            'posting_date' => $document->period_end,
            'document_date' => $document->period_end,
            'document_type' => 'PAYROLL_PAYMENT',
            'document_number' => $documentNo,
            'description' => "Salary bank payment {$document->document_number}",
            'amount' => -$amount,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'user_id' => $userId,
        ]);
    }
}
