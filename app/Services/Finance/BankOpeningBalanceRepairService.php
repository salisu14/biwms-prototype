<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\AccountCategory;
use App\Exceptions\BusinessException;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\PostingTransaction;
use App\Services\AuditTrailService;
use App\Services\CurrencyService;
use App\Services\PostingDateValidator;
use App\Support\DecimalMath;
use Illuminate\Support\Facades\DB;

final class BankOpeningBalanceRepairService
{
    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly PostingDateValidator $postingDateValidator,
        private readonly CurrencyService $currencyService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(BankAccount $bankAccount): array
    {
        return $this->inspect($bankAccount, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function repair(BankAccount $bankAccount, ?int $actorId = null): array
    {
        return DB::transaction(function () use ($bankAccount, $actorId): array {
            $lockedBank = BankAccount::query()->lockForUpdate()->findOrFail($bankAccount->getKey());
            $inspection = $this->inspect($lockedBank, true);

            if ($inspection['correction_exists']) {
                return [...$inspection, 'repaired' => false, 'idempotent' => true];
            }

            $this->postingDateValidator->validate($inspection['posting_date']);

            $transaction = $this->generalLedgerService->postTransaction([
                [
                    'account_id' => $inspection['bank_gl_account_id'],
                    'debit_amount' => $inspection['amount'],
                    'credit_amount' => '0',
                    'description' => 'Correction of bank opening balance G/L classification',
                    'source_type' => 'BANK',
                    'source_number' => $inspection['correction_document_number'],
                ],
                [
                    'account_id' => $inspection['equity_account_id'],
                    'debit_amount' => '0',
                    'credit_amount' => $inspection['amount'],
                    'description' => 'Correction of bank opening balance G/L classification',
                    'source_type' => 'BANK',
                    'source_number' => $inspection['correction_document_number'],
                ],
            ], [
                'business_id' => $inspection['business_id'],
                'posting_date' => $inspection['posting_date'],
                'document_date' => $inspection['posting_date'],
                'document_type' => 'BANK_OPENING_CORRECTION',
                'document_number' => $inspection['correction_document_number'],
                'source_module' => 'finance',
                'source_type' => 'BANK',
                'source_id' => $inspection['bank_account_id'],
                'source_number' => $inspection['correction_document_number'],
                'currency_code' => $inspection['currency_code'],
                'exchange_rate' => $inspection['exchange_rate'],
                'actor_id' => $actorId,
                'transaction_key' => $inspection['correction_key'],
                'idempotency_key' => $inspection['correction_key'],
                'description' => 'Controlled correction for '.$inspection['original_document_number'],
                'reversal_of_transaction_id' => $inspection['original_transaction_id'],
                'reason' => 'Correct same-account bank opening balance G/L classification',
            ]);

            $this->auditTrailService->recordGeneric(
                eventType: 'bank_opening_balance',
                action: 'bank_opening_balance_correction_posted',
                auditable: $lockedBank,
                documentType: 'BANK_OPENING_CORRECTION',
                documentNo: $inspection['correction_document_number'],
                userId: $actorId,
                description: 'Controlled append-only correction for '.$inspection['original_document_number'],
                metadata: [
                    'bank_account_id' => $inspection['bank_account_id'],
                    'original_posting_transaction_id' => $inspection['original_transaction_id'],
                    'original_document_number' => $inspection['original_document_number'],
                    'amount' => $inspection['amount'],
                    'wrong_account_id' => $inspection['bank_account_id'],
                    'corrected_equity_account_id' => $inspection['equity_account_id'],
                    'correction_posting_transaction_id' => $transaction->id,
                    'reason' => 'Correct same-account bank opening balance G/L classification',
                ],
            );

            return [...$inspection, 'repaired' => true, 'idempotent' => false, 'correction_transaction_id' => $transaction->id];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(BankAccount $bankAccount, bool $forApply): array
    {
        $openingLedgers = BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bankAccount->id)
            ->where('document_type', 'OPENING_BALANCE')
            ->where('document_no', 'OB-BANK-'.$bankAccount->id)
            ->where('source_type', 'BANK')
            ->where('source_id', $bankAccount->id)
            ->whereNull('voided_at')
            ->orderBy('id')
            ->get();

        if ($openingLedgers->count() !== 1) {
            throw new BusinessException('The bank opening-balance ledger shape is not uniquely identifiable; no correction is safe.');
        }

        $ledger = $openingLedgers->first();
        $amount = DecimalMath::currency($ledger->amount);
        $ledgerTotal = BankAccountLedgerEntry::query()
            ->where('bank_account_id', $bankAccount->id)
            ->whereNull('voided_at')
            ->sum('amount');

        if (DecimalMath::compare($ledgerTotal, $bankAccount->current_balance) !== 0
            || DecimalMath::compare($ledgerTotal, $bankAccount->available_balance) !== 0
            || DecimalMath::compare($ledger->balance, $amount) !== 0) {
            throw new BusinessException('The bank ledger or cached bank balance does not reconcile; no correction is safe.');
        }

        $originalGlEntry = GlEntry::query()->find($ledger->gl_entry_id);
        $originalTransaction = $originalGlEntry
            ? PostingTransaction::query()->find($originalGlEntry->posting_transaction_id)
            : null;
        if (! $originalTransaction) {
            $originalTransaction = PostingTransaction::query()
                ->where('transaction_key', 'OPENING_BALANCE:BANK:'.$bankAccount->id)
                ->first();
        }

        if (! $originalTransaction) {
            throw new BusinessException('The original bank opening-balance posting transaction could not be found.');
        }

        $lines = $originalTransaction->glEntries()->orderBy('id')->get();
        $sameAccountDefect = $lines->count() === 2
            && $lines->every(fn (GlEntry $line): bool => (int) $line->chart_of_account_id === (int) $bankAccount->gl_account_id)
            && DecimalMath::compare($lines->sum('debit_amount'), $amount) === 0
            && DecimalMath::compare($lines->sum('credit_amount'), $amount) === 0;

        if (! $sameAccountDefect) {
            throw new BusinessException('The historical G/L shape does not match the known same-account bank opening-balance defect.');
        }

        $setup = GeneralLedgerSetup::query()->first();
        $equityAccountId = $setup?->opening_balance_equity_account_id;
        $equityAccount = $equityAccountId ? ChartOfAccount::query()->find($equityAccountId) : null;

        if (! $equityAccount || $equityAccount->id === (int) $bankAccount->gl_account_id
            || $equityAccount->account_category !== AccountCategory::EQUITY
            || ! $equityAccount->allowsDirectPosting()
            || $equityAccount->isSystemControlled()) {
            throw new BusinessException('The configured Opening Balance Equity account is not valid for this correction.');
        }

        $correctionKey = 'BANK_OPENING_CORRECTION:BANK:'.$bankAccount->id.':ORIGINAL_TX:'.$originalTransaction->id;
        $correction = PostingTransaction::query()->where('idempotency_key', $correctionKey)->first();
        if ($correction && ! $forApply) {
            $correction = $correction->load('glEntries');
        }

        return [
            'bank_account_id' => $bankAccount->id,
            'bank_gl_account_id' => (int) $bankAccount->gl_account_id,
            'equity_account_id' => $equityAccount->id,
            'amount' => $amount,
            'currency_code' => $ledger->currency_code ?? $this->currencyService->getLCY()->code,
            'exchange_rate' => $ledger->currency_factor ?? '1',
            'posting_date' => $ledger->posting_date,
            'business_id' => $originalTransaction->business_id,
            'original_document_number' => $ledger->document_no,
            'original_transaction_id' => $originalTransaction->id,
            'correction_document_number' => 'OB-BANK-'.$bankAccount->id.'-CORR',
            'correction_key' => $correctionKey,
            'correction_exists' => $correction !== null,
            'correction_transaction_id' => $correction?->id,
        ];
    }
}
