<?php

namespace App\Services;

use App\Enums\BankAccountLedgerEntryType;
use App\Enums\PettyCashTransactionType;
use App\Enums\PettyCashVoucherStatus;
use App\Enums\SourceType;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\PettyCashVoucher;
use App\Models\User;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class PettyCashPostingService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly BankAccountLedgerService $bankAccountLedgerService,
        private readonly AuditTrailService $auditTrailService,
        private readonly GeneralLedgerService $generalLedgerService,
    ) {}

    public function postVoucher(PettyCashVoucher $voucher, int $userId): void
    {
        Gate::forUser(User::query()->findOrFail($userId))->authorize('post', $voucher);

        DB::transaction(function () use ($voucher, $userId): void {
            $voucher = PettyCashVoucher::query()
                ->with(['fund', 'lines'])
                ->lockForUpdate()
                ->findOrFail($voucher->id);

            $this->validateVoucher($voucher);

            $pettyCashTransactionNumber = $this->nextNumber('PC-TRANS', 'PCT');
            $newBalance = (float) $voucher->fund->current_balance - (float) $voucher->total_amount;

            $voucher->fund->update([
                'current_balance' => $newBalance,
            ]);

            $voucher->fund->transactions()->create([
                'petty_cash_voucher_id' => $voucher->id,
                'transaction_number' => $pettyCashTransactionNumber,
                'date' => $voucher->date,
                'type' => PettyCashTransactionType::PAYMENT,
                'amount' => -abs((float) $voucher->total_amount),
                'running_balance' => $newBalance,
                'chart_of_account_id' => $voucher->fund->chart_of_account_id,
                'description' => "Payment: {$voucher->purpose}",
                'reference_number' => $voucher->voucher_number,
            ]);

            $glLines = [[
                'account_id' => (int) $voucher->fund->chart_of_account_id,
                'debit' => '0.00',
                'credit' => (string) $voucher->total_amount,
                'description' => 'Petty Cash Payment: '.$voucher->purpose,
                'dimensions' => [],
            ]];

            $bankLedgerEntry = $this->createBankLedgerEntryWhenCashAccountIsBank($voucher, $userId);

            foreach ($voucher->lines as $line) {
                $glLines[] = [
                    'account_id' => (int) $line->expense_account_id,
                    'debit' => (string) $line->amount,
                    'credit' => '0.00',
                    'description' => $line->description,
                    'dimensions' => [
                        'shortcut_dimension_1_code' => $line->dimension_department_id,
                        'shortcut_dimension_2_code' => $line->dimension_project_id,
                    ],
                ];
            }

            $this->generalLedgerService->post($glLines, [
                'source_module' => 'petty_cash',
                'source_type' => SourceType::PETTY_CASH->value,
                'source_id' => $voucher->id,
                'source_number' => $voucher->voucher_number,
                'document_type' => 'PETTY_CASH_VOUCHER',
                'document_number' => $voucher->voucher_number,
                'posting_date' => $voucher->date,
                'document_date' => $voucher->date,
                'description' => "Petty cash voucher {$voucher->voucher_number}",
                'actor_id' => $userId,
                'transaction_key' => "petty_cash:voucher:{$voucher->id}:{$voucher->voucher_number}",
                'idempotency_key' => hash('sha256', "petty_cash:voucher:{$voucher->id}:{$voucher->voucher_number}"),
            ]);

            $voucher->update([
                'status' => PettyCashVoucherStatus::POSTED,
                'posted_by_id' => $userId,
                'posted_at' => now(),
            ]);

            $this->auditTrailService->recordPosting(
                auditable: $voucher,
                userId: $userId,
                documentType: 'PETTY_CASH_VOUCHER',
                documentNo: $voucher->voucher_number,
                metadata: [
                    'petty_cash_fund_id' => $voucher->petty_cash_fund_id,
                    'bank_ledger_entry_id' => $bankLedgerEntry?->id,
                    'bank_account_id' => $bankLedgerEntry?->bank_account_id,
                    'amount' => (float) $voucher->total_amount,
                ],
                description: "Posted petty cash voucher {$voucher->voucher_number}",
            );
        });
    }

    private function validateVoucher(PettyCashVoucher $voucher): void
    {
        if ($voucher->status !== PettyCashVoucherStatus::APPROVED) {
            throw new RuntimeException('Only approved petty cash vouchers can be posted.');
        }

        if ((float) $voucher->total_amount <= 0) {
            throw new RuntimeException('Petty cash voucher amount must be greater than zero.');
        }

        if (! $voucher->fund) {
            throw new RuntimeException('A petty cash fund is required before posting.');
        }

        if (empty($voucher->fund->chart_of_account_id)) {
            throw new RuntimeException("Petty Cash Fund '{$voucher->fund->name}' does not have a G/L Account assigned.");
        }

        if ((float) $voucher->fund->current_balance < (float) $voucher->total_amount) {
            throw new RuntimeException("Petty Cash Fund '{$voucher->fund->name}' has insufficient balance.");
        }

        if ($voucher->lines->isEmpty()) {
            throw new RuntimeException('Petty cash voucher must have at least one expense line.');
        }

        $lineTotal = round((float) $voucher->lines->sum('amount'), 2);
        if ($lineTotal !== round((float) $voucher->total_amount, 2)) {
            throw new RuntimeException('Petty cash voucher lines must equal the voucher total.');
        }

        foreach ($voucher->lines as $line) {
            if ((float) $line->amount <= 0) {
                throw new RuntimeException("Voucher line '{$line->description}' amount must be greater than zero.");
            }

            if (empty($line->expense_account_id)) {
                throw new RuntimeException("Voucher line '{$line->description}' does not have an Expense G/L Account assigned.");
            }
        }
    }

    private function createBankLedgerEntryWhenCashAccountIsBank(PettyCashVoucher $voucher, int $userId): ?BankAccountLedgerEntry
    {
        $bankAccount = BankAccount::query()
            ->where('gl_account_id', $voucher->fund->chart_of_account_id)
            ->lockForUpdate()
            ->first();

        if (! $bankAccount) {
            return null;
        }

        $existingEntry = BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bankAccount->id)
            ->where('document_type', 'PETTY_CASH_VOUCHER')
            ->where('document_no', $voucher->voucher_number)
            ->where('source_type', PettyCashVoucher::class)
            ->where('source_id', $voucher->id)
            ->first();

        if ($existingEntry) {
            return $existingEntry;
        }

        return $this->bankAccountLedgerService->postPayment($bankAccount, [
            'amount' => (float) $voucher->total_amount,
            'posting_date' => $voucher->date,
            'document_date' => $voucher->date,
            'document_type' => 'PETTY_CASH_VOUCHER',
            'document_no' => $voucher->voucher_number,
            'description' => "Petty Cash Payment: {$voucher->purpose}",
            'entry_type' => BankAccountLedgerEntryType::WITHDRAWAL,
            'source_type' => PettyCashVoucher::class,
            'source_id' => $voucher->id,
            'source_no' => $voucher->voucher_number,
            'user_id' => $userId,
            'allow_overdraft' => true,
        ]);
    }

    private function nextNumber(string $seriesCode, string $fallbackPrefix): string
    {
        return $this->numberSeriesService->getNextNoFromSeries([$seriesCode], null, $fallbackPrefix);
    }
}
