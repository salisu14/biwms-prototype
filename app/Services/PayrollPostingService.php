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
use App\Services\Finance\GeneralLedgerService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PayrollPostingService
{
    public function __construct(
        private readonly PostingDateValidator $postingDateValidator,
        private readonly GeneralLedgerService $generalLedgerService,
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
            $glLines = [];
            $postedLineIds = [];

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
                    $this->appendPayrollLines($glLines, $line, $employee, $expenseAccount, $netPayAccount, $amount, $description);
                    $postedLineIds[] = $line->id;
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

                    $this->appendPayrollLines($glLines, $line, $employee, $netPayAccount, $liabilityAccount, $amount, $description);
                    $postedLineIds[] = $line->id;
                } elseif ($payCode->type === PayCodeType::BENEFIT) {
                    // Employer Cost: Dr Expense, Cr Liability
                    $expenseAccount = $payCodeAccountId ?? $postingGroup->salaries_account_id;
                    $liabilityAccount = $postingGroup->social_security_account_id;

                    $this->appendPayrollLines($glLines, $line, $employee, $expenseAccount, $liabilityAccount, $amount, $description);
                    $postedLineIds[] = $line->id;
                }
            }

            if ($glLines === []) {
                throw new Exception("Payroll document {$documentNumber} has no positive payroll amounts to post.");
            }

            $transactionKey = "payroll:document:{$document->id}:{$documentNumber}";
            $postingTransaction = $this->generalLedgerService->postTransaction($glLines, [
                'source_module' => 'payroll',
                'source_type' => SourceType::EMPLOYEE->value,
                'source_id' => $document->id,
                'source_number' => $documentNumber,
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'document_type' => 'PAYROLL',
                'document_number' => $documentNumber,
                'description' => "Payroll {$documentNumber}",
                'actor_id' => Auth::id() ?? 1,
                'transaction_key' => $transactionKey,
                'idempotency_key' => hash('sha256', $transactionKey),
            ]);

            foreach ($document->lines->whereIn('id', $postedLineIds) as $line) {
                $glEntry = $postingTransaction->glEntries
                    ->first(fn ($entry): bool => (int) data_get($entry->dimensions, 'payroll_line_id') === (int) $line->id && (float) $entry->debit_amount > 0);

                if ($glEntry) {
                    $this->markLinePosted($line, $glEntry);
                }
            }

            $document->status = PayrollStatus::POSTED;
            $document->save();
        });

        PayrollPosted::dispatch($document->fresh());
    }

    /**
     * @param  array<int, array<string, mixed>>  $glLines
     */
    private function appendPayrollLines(array &$glLines, PayrollLine $line, Employee $employee, int $debitAccountId, int $creditAccountId, float $amount, string $description): void
    {
        $dimensions = ['payroll_line_id' => $line->id];

        $glLines[] = [
            'account_id' => $debitAccountId,
            'debit' => $amount,
            'credit' => '0.00',
            'source_type' => SourceType::EMPLOYEE->value,
            'source_number' => $employee->employee_number,
            'description' => $description,
            'dimensions' => $dimensions,
        ];

        $glLines[] = [
            'account_id' => $creditAccountId,
            'debit' => '0.00',
            'credit' => $amount,
            'source_type' => SourceType::EMPLOYEE->value,
            'source_number' => $employee->employee_number,
            'description' => $description,
            'dimensions' => $dimensions,
        ];
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
