<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountCategory;
use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Enums\CheckType;
use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Exceptions\PostingSetupException;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\User;
use App\Models\VendorLedgerEntry;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BankAccountLedgerService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService,
        private readonly CurrencyService $currencyService,
        private readonly GeneralLedgerService $glService
    ) {}

    /**
     * Post payment to bank account (BC: Post Payment)
     */
    public function postPayment(
        BankAccount $bankAccount,
        array $data,
        ?VendorLedgerEntry $vendorEntry = null
    ): BankAccountLedgerEntry {
        return DB::transaction(function () use ($bankAccount, $data, $vendorEntry) {
            $entryNo = $this->getNextLedgerEntryNumber($data['posting_date'] ?? now());

            $lastBalance = $this->getLastBalance($bankAccount);
            $amount = -abs((float) $data['amount']);
            $newBalance = $lastBalance + $amount;

            if ($newBalance < 0 && ($data['allow_overdraft'] ?? false) !== true) {
                throw new \InvalidArgumentException('Insufficient bank balance for this payment.');
            }

            $currencyCode = $data['currency_code'] ?? $bankAccount->currency?->code;
            $currencyFactor = 1;

            if ($currencyCode && $currencyCode !== $this->currencyService->getLCY()->code) {
                $currency = $this->currencyService->getByCode($currencyCode);
                $currencyFactor = $currency->getExchangeRate($data['posting_date'] ?? now());
            }

            $entry = BankAccountLedgerEntry::create([
                'entry_number' => $entryNo,
                'bank_account_id' => $bankAccount->id,
                'bank_account_no' => $bankAccount->account_number,
                'posting_date' => $data['posting_date'] ?? now(),
                'document_date' => $data['document_date'] ?? now(),
                'document_type' => $data['document_type'] ?? 'payment',
                'document_no' => $data['document_no'] ?? $this->numberSeriesService->getNextNo('PAYMENT'),
                'external_document_no' => $data['external_document_no'] ?? null,
                'description' => $data['description'],
                'entry_type' => $data['entry_type'] ?? BankAccountLedgerEntryType::WITHDRAWAL,
                'check_type' => $data['check_type'] ?? null,
                'check_no' => $data['check_no'] ?? null,
                'check_date' => $data['check_date'] ?? null,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'currency_factor' => $currencyFactor,
                'balance' => $newBalance,
                'balance_lcy' => $newBalance * $currencyFactor,
                'status' => BankAccountLedgerEntryStatus::OPEN,
                'open' => true,
                'vendor_ledger_entry_id' => $vendorEntry?->id,
                'source_type' => $vendorEntry ? 'vendor' : ($data['source_type'] ?? null),
                'source_id' => $vendorEntry?->vendor_id ?? $data['source_id'] ?? null,
                'source_no' => $data['source_no'] ?? null,
                'user_id' => $data['user_id'] ?? Auth::id() ?? 1,
                'shortcut_dimension_1_code' => $data['dimension_1'] ?? null,
                'shortcut_dimension_2_code' => $data['dimension_2'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
            ]);

            if ($vendorEntry) {
                $this->applyToVendorLedger($entry, $vendorEntry);
            }

            $bankAccount->update([
                'current_balance' => $newBalance,
                'available_balance' => $newBalance,
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Post deposit to bank account
     */
    public function postDeposit(
        BankAccount $bankAccount,
        array $data
    ): BankAccountLedgerEntry {
        return DB::transaction(function () use ($bankAccount, $data) {
            $entryNo = $this->getNextLedgerEntryNumber($data['posting_date'] ?? now());
            $lastBalance = $this->getLastBalance($bankAccount);
            $amount = abs((float) $data['amount']);
            $newBalance = $lastBalance + $amount;
            $currencyCode = $data['currency_code'] ?? $bankAccount->currency?->code;
            $currencyFactor = (float) ($data['currency_factor'] ?? 1);

            $entry = BankAccountLedgerEntry::create([
                'entry_number' => $entryNo,
                'bank_account_id' => $bankAccount->id,
                'bank_account_no' => $bankAccount->account_number,
                'posting_date' => $data['posting_date'] ?? now(),
                'document_date' => $data['document_date'] ?? now(),
                'document_type' => $data['document_type'] ?? 'deposit',
                'document_no' => $data['document_no'] ?? $this->numberSeriesService->getNextNo('DEPOSIT'),
                'external_document_no' => $data['external_document_no'] ?? null,
                'description' => $data['description'],
                'entry_type' => BankAccountLedgerEntryType::DEPOSIT,
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'currency_factor' => $currencyFactor,
                'balance' => $newBalance,
                'balance_lcy' => $newBalance * $currencyFactor,
                'status' => BankAccountLedgerEntryStatus::OPEN,
                'open' => true,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'source_no' => $data['source_no'] ?? null,
                'user_id' => $data['user_id'] ?? Auth::id() ?? 1,
                'shortcut_dimension_1_code' => $data['dimension_1'] ?? null,
                'shortcut_dimension_2_code' => $data['dimension_2'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
            ]);

            $bankAccount->update([
                'current_balance' => $newBalance,
                'available_balance' => $newBalance,
            ]);

            return $entry->fresh();
        });
    }

    /**
     * Fund a bank account through the controlled opening-balance workflow.
     *
     * The bank ledger remains the source for the running balance while the
     * posting kernel owns the balanced bank/equity G/L transaction.
     */
    public function postOpeningBalance(
        BankAccount $bankAccount,
        string|float|int $amount,
        ?int $offsetAccountId,
        \DateTimeInterface|string $postingDate,
        string $description,
        ?string $externalDocumentNo = null,
        ?int $userId = null,
        array $dimensions = [],
    ): BankAccountLedgerEntry {
        $actorId = $userId ?? Auth::id();

        if (! $actorId) {
            throw new AuthorizationException('Authentication is required to post a bank opening balance.');
        }

        Gate::forUser(User::query()->findOrFail($actorId))->authorize('openingBalance', $bankAccount);

        return DB::transaction(function () use ($bankAccount, $amount, $postingDate, $description, $externalDocumentNo, $userId, $dimensions): BankAccountLedgerEntry {
            $bankAccount = BankAccount::query()
                ->with(['glAccount', 'currency'])
                ->lockForUpdate()
                ->findOrFail($bankAccount->getKey());

            if (! $bankAccount->active) {
                throw new BusinessException('The bank account is inactive.', title: 'Opening balance was not posted');
            }

            if (! $bankAccount->glAccount || ! $bankAccount->glAccount->allowsDirectPosting()) {
                throw new BusinessException('The bank account must have an active, direct-posting G/L account.', title: 'Opening balance was not posted');
            }

            if ((float) $amount <= 0) {
                throw new BusinessException('Opening balance amount must be greater than zero.', title: 'Opening balance was not posted');
            }

            $setup = GeneralLedgerSetup::query()->lockForUpdate()->first();
            $configuredOffsetAccountId = $setup?->opening_balance_equity_account_id;

            if (! $configuredOffsetAccountId) {
                throw new BusinessException('Configure an Opening Balance Equity account before posting a bank opening balance.', title: 'Opening balance was not posted');
            }

            $offsetAccount = ChartOfAccount::query()->lockForUpdate()->find((int) $configuredOffsetAccountId);
            if (! $offsetAccount) {
                throw new BusinessException('The configured Opening Balance Equity account does not exist.', title: 'Opening balance was not posted');
            }

            if ($offsetAccount->id === (int) $bankAccount->gl_account_id) {
                throw new BusinessException('Opening Balance Equity account cannot be the same as the bank G/L account.', title: 'Opening balance was not posted');
            }

            if ($offsetAccount->account_category !== AccountCategory::EQUITY
                || ! $offsetAccount->allowsDirectPosting()
                || $offsetAccount->isSystemControlled()) {
                throw new BusinessException('The configured Opening Balance Equity account must be an active, direct-posting, non-system-controlled Equity account.', title: 'Opening balance was not posted');
            }

            app(PostingDateValidator::class)->validate($postingDate);

            $documentNo = 'OB-BANK-'.$bankAccount->getKey();
            $alreadyPosted = BankAccountLedgerEntry::query()
                ->where('bank_account_id', $bankAccount->id)
                ->where('document_type', 'OPENING_BALANCE')
                ->where('source_type', SourceType::BANK->value)
                ->where('source_id', $bankAccount->id)
                ->exists();

            if ($alreadyPosted) {
                throw new BusinessException('An opening balance has already been posted for this bank account.', title: 'Opening balance was not posted');
            }

            $currencyCode = $bankAccount->currency?->code ?? $this->currencyService->getLCY()->code;
            $exchangeRate = $currencyCode === $this->currencyService->getLCY()->code
                ? 1
                : $bankAccount->currency?->getExchangeRate($postingDate) ?? 1;
            $actorId = $userId ?? Auth::id();
            $transaction = $this->glService->postTransaction([
                [
                    'account_id' => $bankAccount->gl_account_id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => $description,
                    'dimensions' => $dimensions,
                    'source_type' => SourceType::BANK->value,
                    'source_number' => $documentNo,
                ],
                [
                    'account_id' => $offsetAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => $description,
                    'dimensions' => $dimensions,
                    'source_type' => SourceType::BANK->value,
                    'source_number' => $documentNo,
                ],
            ], [
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'document_type' => 'OPENING_BALANCE',
                'document_number' => $documentNo,
                'source_module' => 'finance',
                'source_type' => SourceType::BANK->value,
                'source_id' => $bankAccount->id,
                'source_number' => $documentNo,
                'external_document_number' => $externalDocumentNo,
                'description' => $description,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'dimensions' => $dimensions,
                'actor_id' => $actorId,
                'idempotency_key' => 'OPENING_BALANCE:BANK:'.$bankAccount->id,
                'transaction_key' => 'OPENING_BALANCE:BANK:'.$bankAccount->id,
            ]);

            $bankEntry = $this->postDeposit($bankAccount, [
                'amount' => $amount,
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'document_type' => 'OPENING_BALANCE',
                'document_no' => $documentNo,
                'external_document_no' => $externalDocumentNo,
                'description' => $description,
                'currency_code' => $currencyCode,
                'currency_factor' => $exchangeRate,
                'source_type' => SourceType::BANK->value,
                'source_id' => $bankAccount->id,
                'source_no' => $documentNo,
                'user_id' => $actorId,
                'dimensions' => $dimensions,
            ]);

            $bankGlEntry = $transaction->glEntries
                ->firstWhere('chart_of_account_id', $bankAccount->gl_account_id);
            $bankEntry->update(['gl_entry_id' => $bankGlEntry?->id]);

            app(AuditTrailService::class)->recordGeneric(
                eventType: 'opening_balance',
                action: 'bank_opening_balance_posted',
                auditable: $bankAccount,
                documentType: 'OPENING_BALANCE',
                documentNo: $documentNo,
                userId: $actorId,
                description: "Opening balance posted for bank account {$bankAccount->account_code}",
                metadata: [
                    'amount' => $amount,
                    'posting_date' => (string) $postingDate,
                    'offset_account_id' => $offsetAccount->id,
                    'bank_ledger_entry_id' => $bankEntry->id,
                    'posting_transaction_id' => $transaction->id,
                ],
            );

            return $bankEntry->fresh();
        });
    }

    /**
     * Post transfer between bank accounts
     */
    public function postTransfer(
        BankAccount $fromBank,
        BankAccount $toBank,
        float $amount,
        array $data
    ): array {
        return DB::transaction(function () use ($fromBank, $toBank, $amount, $data) {
            $documentNo = $data['document_no'] ?? $this->numberSeriesService->getNextNo('TRANSFER');
            $postingDate = $data['posting_date'] ?? now();

            // Withdrawal from source
            $withdrawal = $this->postPayment($fromBank, [
                'amount' => -abs($amount),
                'posting_date' => $postingDate,
                'document_no' => $documentNo,
                'description' => "Transfer to {$toBank->account_number}: ".($data['description'] ?? ''),
                'entry_type' => BankAccountLedgerEntryType::TRANSFER,
            ]);

            // Deposit to destination
            $deposit = $this->postDeposit($toBank, [
                'amount' => abs($amount),
                'posting_date' => $postingDate,
                'document_no' => $documentNo,
                'description' => "Transfer from {$fromBank->account_number}: ".($data['description'] ?? ''),
            ]);

            // Link entries
            $withdrawal->update(['transfer_entry_id' => $deposit->id]);
            $deposit->update(['transfer_entry_id' => $withdrawal->id]);

            return [
                'withdrawal' => $withdrawal,
                'deposit' => $deposit,
            ];
        });
    }

    /**
     * Post check payment
     */
    public function postCheck(
        BankAccount $bankAccount,
        array $data,
        ?VendorLedgerEntry $vendorEntry = null
    ): BankAccountLedgerEntry {
        $checkNo = $data['check_no'] ?? $this->getNextCheckNo($bankAccount);

        return $this->postPayment($bankAccount, array_merge($data, [
            'entry_type' => BankAccountLedgerEntryType::CHECK,
            'check_type' => $data['check_type'] ?? CheckType::COMPUTER_CHECK,
            'check_no' => $checkNo,
            'check_date' => $data['check_date'] ?? now(),
        ]), $vendorEntry);
    }

    /**
     * Void a check
     */
    public function voidCheck(BankAccountLedgerEntry $entry, string $reason): void
    {
        if ($entry->entry_type !== BankAccountLedgerEntryType::CHECK) {
            throw new \InvalidArgumentException('Only checks can be voided');
        }

        if (! $entry->canVoid()) {
            throw new \InvalidArgumentException('Check cannot be voided');
        }

        DB::transaction(function () use ($entry, $reason) {
            // Void the entry
            $entry->void($reason);

            // Reverse G/L entry
            if ($entry->glEntry) {
                $this->glService->reverseEntry($entry->glEntry, 'Void check '.$entry->check_no);
            }

            $entry->bankAccount->increment('current_balance', abs($entry->amount));
            $entry->bankAccount->increment('available_balance', abs($entry->amount));
        });
    }

    /**
     * Get bank account balance as of date
     */
    public function getBalanceAsOf(BankAccount $bankAccount, \DateTime $date): float
    {
        return BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->where('posting_date', '<=', $date)
            ->orderByDesc('posting_date')
            ->orderByDesc('entry_number')
            ->value('balance') ?? 0;
    }

    /**
     * Get uncleared transactions
     */
    public function getUnclearedTransactions(BankAccount $bankAccount): array
    {
        $deposits = BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->where('entry_type', BankAccountLedgerEntryType::DEPOSIT)
            ->where('open', true)
            ->whereNull('statement_no')
            ->sum('amount');

        $withdrawals = BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->whereIn('entry_type', [
                BankAccountLedgerEntryType::WITHDRAWAL,
                BankAccountLedgerEntryType::CHECK,
            ])
            ->where('open', true)
            ->whereNull('statement_no')
            ->sum('amount');

        return [
            'uncleared_deposits' => $deposits,
            'uncleared_withdrawals' => abs($withdrawals),
            'net_uncleared' => $deposits + $withdrawals,
        ];
    }

    /**
     * Get check register
     */
    public function getCheckRegister(
        BankAccount $bankAccount,
        ?\DateTime $from = null,
        ?\DateTime $to = null
    ): Collection {
        $query = BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->checks()
            ->with(['vendorLedgerEntry.vendor']);

        if ($from) {
            $query->where('posting_date', '>=', $from);
        }
        if ($to) {
            $query->where('posting_date', '<=', $to);
        }

        return $query->orderBy('check_no')->get();
    }

    // Private methods
    private function getLastBalance(BankAccount $bankAccount): float
    {
        return (float) (BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->orderByDesc('posting_date')
            ->orderByDesc('entry_number')
            ->value('balance') ?? $bankAccount->current_balance ?? 0);
    }

    private function getNextCheckNo(BankAccount $bankAccount): string
    {
        $lastCheck = BankAccountLedgerEntry::forBankAccount($bankAccount->id)
            ->whereNotNull('check_no')
            ->orderByDesc('check_no')
            ->first();

        if (! $lastCheck) {
            return $bankAccount->next_check_number ?? '1000';
        }

        return (string) ((int) $lastCheck->check_no + 1);
    }

    private function getNextLedgerEntryNumber(\DateTimeInterface|string|null $postingDate = null): int
    {
        try {
            $nextNumber = $this->numberSeriesService->getNextNo(
                'BANK-LEDGER',
                $postingDate instanceof \DateTimeInterface ? $postingDate : null
            );

            if (is_numeric($nextNumber)) {
                return (int) $nextNumber;
            }

            if (preg_match('/(\d+)(?!.*\d)/', $nextNumber, $matches) === 1) {
                return (int) $matches[1];
            }

            throw new \UnexpectedValueException("BANK-LEDGER number [{$nextNumber}] does not contain a numeric sequence.");
        } catch (\Throwable $exception) {
            throw new PostingSetupException(
                'Missing or invalid BANK-LEDGER number series configuration.',
                metadata: ['number_series' => 'BANK-LEDGER'],
                previous: $exception,
            );
        }
    }

    private function applyToVendorLedger(
        BankAccountLedgerEntry $bankEntry,
        VendorLedgerEntry $vendorEntry
    ): void {
        // Update vendor entry with payment application
        $vendorEntry->update([
            'remaining_amount' => max(0, $vendorEntry->remaining_amount - abs($bankEntry->amount)),
            'open' => $vendorEntry->remaining_amount > abs($bankEntry->amount),
        ]);

        // Create detailed application entry if needed
        // This links the bank payment to the vendor invoice
    }
}
