<?php

namespace App\Services;

use App\Enums\PettyCashTransactionType;
use App\Enums\PettyCashVoucherStatus;
use App\Enums\SourceType;
use App\Models\GlEntry;
use App\Models\PettyCashVoucher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Throwable;

class PettyCashPostingService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService
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
            $glTransactionNumber = $this->nextGlTransactionNumber();
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

            $this->createGlEntry(
                accountId: (int) $voucher->fund->chart_of_account_id,
                debitAmount: 0,
                creditAmount: (float) $voucher->total_amount,
                voucher: $voucher,
                transactionNumber: $glTransactionNumber,
                description: 'Petty Cash Payment: '.$voucher->purpose,
                userId: $userId,
            );

            foreach ($voucher->lines as $line) {
                $this->createGlEntry(
                    accountId: (int) $line->expense_account_id,
                    debitAmount: (float) $line->amount,
                    creditAmount: 0,
                    voucher: $voucher,
                    transactionNumber: $glTransactionNumber,
                    description: $line->description,
                    userId: $userId,
                    dimension1: $line->dimension_department_id,
                    dimension2: $line->dimension_project_id,
                );
            }

            $voucher->update([
                'status' => PettyCashVoucherStatus::POSTED,
                'posted_by_id' => $userId,
                'posted_at' => now(),
            ]);
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

    private function createGlEntry(
        int $accountId,
        float $debitAmount,
        float $creditAmount,
        PettyCashVoucher $voucher,
        int $transactionNumber,
        string $description,
        int $userId,
        ?int $dimension1 = null,
        ?int $dimension2 = null,
    ): void {
        GlEntry::create([
            'entry_number' => $this->nextGlEntryNumber(),
            'transaction_number' => $transactionNumber,
            'amount' => $debitAmount - $creditAmount,
            'posting_date' => $voucher->date,
            'document_type' => 'PETTY_CASH_VOUCHER',
            'document_number' => $voucher->voucher_number,
            'document_date' => $voucher->date,
            'description' => $description,
            'debit_amount' => $debitAmount,
            'credit_amount' => $creditAmount,
            'chart_of_account_id' => $accountId,
            'source_type' => SourceType::GENERAL_JOURNAL,
            'source_number' => $voucher->voucher_number,
            'sourceable_id' => $voucher->id,
            'sourceable_type' => PettyCashVoucher::class,
            'shortcut_dimension_1_code' => $dimension1,
            'shortcut_dimension_2_code' => $dimension2,
            'user_id' => $userId,
        ]);
    }

    private function nextNumber(string $seriesCode, string $fallbackPrefix): string
    {
        try {
            $nextNumber = $this->numberSeriesService->tryGetNextNo($seriesCode);

            if (! empty($nextNumber)) {
                return $nextNumber;
            }
        } catch (Throwable) {
            //
        }

        return $fallbackPrefix.'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function nextGlEntryNumber(): int
    {
        return ((int) (GlEntry::query()->max('entry_number') ?? 0)) + 1;
    }

    private function nextGlTransactionNumber(): int
    {
        return ((int) (GlEntry::query()->max('transaction_number') ?? 0)) + 1;
    }
}
