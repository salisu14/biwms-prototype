<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\PostingIntent;
use App\Accounting\PostingIntentLine;
use App\Enums\PostingIntentLineType;
use App\Enums\PostingLcyOnlyReason;
use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\GlEntry;
use App\Models\PostingTransaction;
use App\Services\AuditTrailService;
use App\Services\PostingDateValidator;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use App\Support\DocumentCurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class GeneralLedgerPostingKernel
{
    public function __construct(
        private readonly LedgerSequenceAllocator $sequenceAllocator,
        private readonly PostingDateValidator $postingDateValidator,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function post(PostingIntent $intent): PostingTransaction
    {
        return DB::transaction(function () use ($intent): PostingTransaction {
            $existing = PostingTransaction::query()
                ->where('idempotency_key', $intent->idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->assertIdempotentReplayMatches($intent, $existing);

                return $existing->load('glEntries');
            }

            $this->validateIntent($intent);

            $currencyContext = $this->currencyAwareContext($intent);

            $transactionNumber = $this->sequenceAllocator->nextGlTransactionNumber();
            $postingTransaction = PostingTransaction::query()->create([
                'business_id' => $intent->businessId,
                'source_module' => $intent->sourceModule,
                'source_type' => $intent->sourceType,
                'source_id' => $intent->sourceId,
                'source_number' => $intent->sourceNumber,
                'document_type' => $intent->documentType,
                'document_number' => $intent->documentNumber,
                'external_document_number' => $intent->externalDocumentNumber,
                'transaction_key' => $intent->transactionKey,
                'idempotency_key' => $intent->idempotencyKey,
                'transaction_number' => $transactionNumber,
                'posting_date' => $intent->postingDate,
                'document_date' => $intent->documentDate,
                'currency_code' => $currencyContext['code'] ?? $intent->currencyCode,
                'exchange_rate' => $currencyContext['factor'] ?? $intent->exchangeRate,
                'economic_fingerprint' => $currencyContext !== null
                    ? $this->economicFingerprint($intent, $currencyContext)
                    : null,
                'dimensions' => $intent->dimensions,
                'status' => 'completed',
                'actor_id' => $intent->actorId,
                'reversal_of_transaction_id' => $intent->reversalOfTransactionId,
                'reason' => $intent->reason,
                'description' => $intent->description,
            ]);

            foreach ($intent->lines as $line) {
                $this->createGlEntry($intent, $line, $postingTransaction, $transactionNumber, $currencyContext);
            }

            $this->auditTrailService->recordGeneric(
                eventType: 'posting',
                action: 'gl_transaction_posted',
                auditable: $postingTransaction,
                documentType: $intent->documentType,
                documentNo: $intent->documentNumber,
                userId: $intent->actorId,
                description: "Posted {$intent->sourceModule} transaction {$intent->documentNumber}",
                metadata: [
                    'idempotency_key' => $intent->idempotencyKey,
                    'transaction_number' => $transactionNumber,
                    'business_id' => $intent->businessId,
                ],
            );

            return $postingTransaction->load('glEntries');
        });
    }

    /**
     * Side-effect-free preflight for callers that want to fail before they
     * create any of their own surrounding accounting rows.
     *
     * Applies exactly the same pre-write decision as post() without locking or
     * writing: an existing idempotency transaction is checked with the shared
     * replay compatibility rules, otherwise the intent is validated as a new
     * posting. It deliberately does not duplicate the replay rule set.
     */
    public function preflight(PostingIntent $intent): void
    {
        $existing = PostingTransaction::query()
            ->where('idempotency_key', $intent->idempotencyKey)
            ->first();

        if ($existing) {
            $this->assertIdempotentReplayMatches($intent, $existing);

            return;
        }

        $this->validateIntent($intent);
    }

    /**
     * Append the accounting-layer-derived mirror of a verified pre-kernel
     * legacy one-sided G/L row so its historical transaction group becomes
     * structurally balanced.
     *
     * The correction is fully derived from the original entry: the account,
     * business, source context, transaction identity, posting date and amounts
     * all come from the persisted legacy row. Callers cannot supply an
     * account, amount, business, source or transaction, so this cannot be used
     * to create arbitrary G/L economics. The original row is never modified.
     *
     * The neutralizer transaction currency is derived from the original row's
     * currency when present. The optional incident currency is only used as a
     * fallback for legacy rows that carry no currency, and is validated to be a
     * configured currency consistent with the original row; it never alters
     * amounts, exchange rates, or LCY figures.
     */
    public function appendLegacyNeutralizer(
        GlEntry $originalEntry,
        ?int $actorId = null,
        ?string $incidentIdentifier = null,
        ?string $reason = null,
        ?string $incidentCurrencyCode = null,
    ): GlEntry {
        return DB::transaction(function () use ($originalEntry, $actorId, $incidentIdentifier, $reason, $incidentCurrencyCode): GlEntry {
            $original = GlEntry::query()
                ->lockForUpdate()
                ->findOrFail($originalEntry->getKey());

            $this->assertLegacyOneSidedOriginal($original);

            $existingCorrections = GlEntry::query()
                ->where('reversal_of_gl_entry_id', $original->getKey())
                ->lockForUpdate()
                ->get();

            if ($existingCorrections->count() > 1) {
                throw new BusinessException('Multiple legacy neutralizer entries already exist for G/L entry '.$original->getKey().'; refusing to append another.');
            }

            if ($existingCorrections->count() === 1) {
                $this->assertMirrorsOriginal($existingCorrections->first(), $original);

                return $existingCorrections->first();
            }

            $this->assertNeutralizableAccount($original);

            $postingTransaction = $this->legacyNeutralizerTransaction($original, $actorId, $incidentIdentifier, $reason, $incidentCurrencyCode);
            $correction = $this->createLegacyNeutralizerEntry($original, $postingTransaction, $actorId, $incidentIdentifier, $reason);

            $this->assertTransactionGroupBalanced($original);

            $this->auditTrailService->recordGeneric(
                eventType: 'posting',
                action: 'legacy_gl_transaction_correction_appended',
                auditable: $postingTransaction,
                documentType: $correction->document_type,
                documentNo: $correction->document_number,
                userId: $actorId,
                description: 'Appended legacy G/L neutralizer for transaction '.$original->transaction_number,
                metadata: [
                    'original_gl_entry_id' => $original->getKey(),
                    'correction_gl_entry_id' => $correction->getKey(),
                    'transaction_number' => $original->transaction_number,
                    'incident_identifier' => $incidentIdentifier,
                ],
            );

            return $correction;
        });
    }

    private function assertLegacyOneSidedOriginal(GlEntry $original): void
    {
        if ($original->posting_transaction_id !== null) {
            throw new BusinessException('G/L entry '.$original->getKey().' is linked to a posting transaction and is not a pre-kernel legacy row.');
        }

        if ($original->transaction_number === null) {
            throw new BusinessException('G/L entry '.$original->getKey().' has no transaction number and cannot be neutralized.');
        }

        $debit = DecimalMath::currency($original->debit_amount);
        $credit = DecimalMath::currency($original->credit_amount);

        $isDebitOnly = DecimalMath::isPositive($debit) && DecimalMath::isZero($credit);
        $isCreditOnly = DecimalMath::isPositive($credit) && DecimalMath::isZero($debit);

        if (! $isDebitOnly && ! $isCreditOnly) {
            throw new BusinessException('Only a one-sided legacy G/L entry can be neutralized; entry '.$original->getKey().' is not one-sided.');
        }
    }

    private function assertNeutralizableAccount(GlEntry $original): void
    {
        $account = ChartOfAccount::query()->find($original->chart_of_account_id);

        if (! $account) {
            throw new BusinessException('The legacy G/L entry account no longer exists; refusing to append a neutralizer.');
        }

        if (Schema::hasColumn($account->getTable(), 'business_id')
            && $account->business_id !== null
            && $original->business_id !== null
            && (int) $account->business_id !== (int) $original->business_id) {
            throw new BusinessException('The legacy G/L entry account belongs to another business; refusing to append a neutralizer.');
        }

        if (! $account->allowsDirectPosting()) {
            throw new BusinessException('The legacy G/L entry account does not allow direct posting; refusing to append a neutralizer.');
        }
    }

    private function assertMirrorsOriginal(GlEntry $correction, GlEntry $original): void
    {
        $originalDebit = DecimalMath::currency($original->debit_amount);
        $originalCredit = DecimalMath::currency($original->credit_amount);
        $expectedDebit = DecimalMath::isPositive($originalDebit) ? '0' : $originalCredit;
        $expectedCredit = DecimalMath::isPositive($originalDebit) ? $originalDebit : '0';

        if ((int) $correction->chart_of_account_id !== (int) $original->chart_of_account_id
            || (int) $correction->business_id !== (int) $original->business_id
            || DecimalMath::compare($correction->debit_amount, $expectedDebit) !== 0
            || DecimalMath::compare($correction->credit_amount, $expectedCredit) !== 0) {
            throw new BusinessException('An existing legacy neutralizer for G/L entry '.$original->getKey().' does not mirror the original row; refusing to proceed.');
        }
    }

    /**
     * Resolve the neutralizer transaction currency without reintroducing a
     * generic caller-controlled field: the persisted legacy row's currency is
     * authoritative when present, and the optional incident currency is only a
     * fallback that must be configured and consistent with the original row.
     */
    private function resolveNeutralizerCurrencyCode(GlEntry $original, ?string $incidentCurrencyCode): string
    {
        $originalCode = null;

        if ($original->currency_id !== null) {
            $originalCode = Currency::query()->find($original->currency_id)?->code;

            if ($originalCode === null || trim((string) $originalCode) === '') {
                throw new BusinessException('The legacy G/L entry references an unknown currency; refusing to append a neutralizer.');
            }

            $originalCode = strtoupper(trim((string) $originalCode));
        }

        $incidentCode = $incidentCurrencyCode !== null ? strtoupper(trim($incidentCurrencyCode)) : null;
        $incidentCode = $incidentCode !== '' ? $incidentCode : null;

        if ($originalCode !== null && $incidentCode !== null && $originalCode !== $incidentCode) {
            throw new BusinessException('The incident currency does not match the legacy G/L entry currency; refusing to append a neutralizer.');
        }

        $currencyCode = $originalCode ?? $incidentCode;

        if ($currencyCode === null) {
            throw new BusinessException('The neutralizer currency cannot be resolved from the legacy G/L entry; refusing to append a neutralizer.');
        }

        if (! Currency::query()->whereRaw('UPPER(code) = ?', [$currencyCode])->exists()) {
            throw new BusinessException('The neutralizer currency '.$currencyCode.' is not a configured currency; refusing to append a neutralizer.');
        }

        return $currencyCode;
    }

    private function legacyNeutralizerTransaction(
        GlEntry $original,
        ?int $actorId,
        ?string $incidentIdentifier,
        ?string $reason,
        ?string $incidentCurrencyCode,
    ): PostingTransaction {
        $transactionNumber = (int) $original->transaction_number;
        $currencyCode = $this->resolveNeutralizerCurrencyCode($original, $incidentCurrencyCode);
        $markerKey = self::legacyNeutralizerTransactionKey($transactionNumber);

        $marker = PostingTransaction::query()
            ->where('idempotency_key', $markerKey)
            ->lockForUpdate()
            ->first();

        if ($marker) {
            if ((int) $marker->transaction_number !== $transactionNumber
                || $marker->document_type !== $original->document_type
                || $marker->document_number !== $original->document_number) {
                throw new BusinessException('The legacy neutralizer transaction does not match the original historical row; refusing to append.');
            }

            return $marker;
        }

        $conflicting = PostingTransaction::query()
            ->where('transaction_number', $transactionNumber)
            ->lockForUpdate()
            ->first();

        if ($conflicting) {
            throw new BusinessException('Transaction number '.$transactionNumber.' is already owned by another posting transaction; refusing to attach a legacy neutralizer.');
        }

        return PostingTransaction::query()->create([
            'business_id' => $original->business_id,
            'source_module' => $original->source_module ?: 'finance',
            'source_type' => $original->source_type instanceof SourceType ? $original->source_type->value : $original->source_type,
            'source_id' => $original->source_id,
            'source_number' => $original->source_number ?: $original->document_number,
            'document_type' => $original->document_type,
            'document_number' => $original->document_number,
            'external_document_number' => $original->external_document_number,
            'transaction_key' => $markerKey,
            'idempotency_key' => $markerKey,
            'transaction_number' => $transactionNumber,
            'posting_date' => $original->posting_date,
            'document_date' => $original->document_date,
            'currency_code' => $currencyCode,
            'exchange_rate' => $original->exchange_rate ?? '1',
            'dimensions' => $this->legacyNeutralizerDimensions($original, $incidentIdentifier),
            'status' => 'completed',
            'actor_id' => $actorId,
            'reason' => $reason,
            'description' => 'Legacy G/L neutralizer for transaction '.$transactionNumber,
        ]);
    }

    private function createLegacyNeutralizerEntry(
        GlEntry $original,
        PostingTransaction $postingTransaction,
        ?int $actorId,
        ?string $incidentIdentifier,
        ?string $reason,
    ): GlEntry {
        $originalDebit = DecimalMath::currency($original->debit_amount);
        $correctionDebit = DecimalMath::isPositive($originalDebit) ? '0' : DecimalMath::currency($original->credit_amount);
        $correctionCredit = DecimalMath::isPositive($originalDebit) ? $originalDebit : '0';
        $amount = DecimalMath::currency(DecimalMath::sub($correctionDebit, $correctionCredit, DecimalPrecision::CURRENCY_SCALE));
        $entryKey = self::legacyNeutralizerEntryKey((int) $original->getKey());

        return GlEntry::query()->create([
            'entry_number' => $this->sequenceAllocator->nextGlEntryNumber(),
            'transaction_number' => $original->transaction_number,
            'posting_transaction_id' => $postingTransaction->getKey(),
            'business_id' => $original->business_id,
            'chart_of_account_id' => $original->chart_of_account_id,
            'general_business_posting_group_id' => $original->general_business_posting_group_id,
            'debit_amount' => $correctionDebit,
            'debit_amount_lcy' => $correctionDebit,
            'credit_amount' => $correctionCredit,
            'credit_amount_lcy' => $correctionCredit,
            'amount' => $amount,
            'amount_lcy' => $amount,
            'currency_id' => $original->currency_id,
            'exchange_rate' => $original->exchange_rate ?? '1',
            'source_type' => $original->source_type instanceof SourceType ? $original->source_type->value : $original->source_type,
            'source_module' => $original->source_module,
            'source_id' => $original->source_id,
            'source_number' => $original->source_number,
            'document_type' => $original->document_type,
            'document_number' => $original->document_number,
            'external_document_number' => $original->external_document_number,
            'idempotency_key' => $entryKey,
            'transaction_key' => $entryKey,
            'posting_group_source' => 'legacy_gl_neutralizer',
            'cost_component' => $original->cost_component,
            'document_date' => $original->document_date,
            'posting_date' => $original->posting_date,
            'user_id' => $actorId,
            'description' => 'Neutralize malformed legacy one-sided G/L entry '.$original->getKey(),
            'comment' => $reason ?? 'Append-only neutralizer for legacy malformed G/L entry; original row preserved.',
            'dimensions' => $this->legacyNeutralizerDimensions($original, $incidentIdentifier),
            'item_ledger_entry_id' => $original->item_ledger_entry_id,
            'cust_ledger_entry_id' => $original->cust_ledger_entry_id,
            'vendor_ledger_entry_id' => $original->vendor_ledger_entry_id,
            'reversal_of_gl_entry_id' => $original->getKey(),
            'shortcut_dimension_1_code' => $original->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $original->shortcut_dimension_2_code,
        ]);
    }

    private function assertTransactionGroupBalanced(GlEntry $original): void
    {
        $totals = GlEntry::query()
            ->where('transaction_number', $original->transaction_number)
            ->where('document_type', $original->document_type)
            ->where('document_number', $original->document_number)
            ->selectRaw('COALESCE(SUM(debit_amount), 0) as debit_total, COALESCE(SUM(credit_amount), 0) as credit_total')
            ->first();

        $debit = DecimalMath::currency($totals->debit_total ?? '0');
        $credit = DecimalMath::currency($totals->credit_total ?? '0');

        if (DecimalMath::compare($debit, $credit) !== 0) {
            throw new BusinessException('The legacy neutralizer did not balance transaction '.$original->transaction_number.'; refusing to append.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyNeutralizerDimensions(GlEntry $original, ?string $incidentIdentifier): array
    {
        $dimensions = is_array($original->dimensions) ? $original->dimensions : [];
        $dimensions['neutralizes_gl_entry_id'] = (int) $original->getKey();

        if ($incidentIdentifier !== null && $incidentIdentifier !== '') {
            $dimensions['incident_identifier'] = $incidentIdentifier;
        }

        return $dimensions;
    }

    private static function legacyNeutralizerEntryKey(int $originalGlEntryId): string
    {
        return 'LEGACY_GL_NEUTRALIZER:'.$originalGlEntryId;
    }

    private static function legacyNeutralizerTransactionKey(int $transactionNumber): string
    {
        return 'LEGACY_GL_NEUTRALIZER_TX:'.$transactionNumber;
    }

    private function validateIntent(PostingIntent $intent): void
    {
        if ($intent->lines === []) {
            throw ValidationException::withMessages(['lines' => 'Posting intent must contain at least one G/L line.']);
        }

        foreach ([
            'source_module' => $intent->sourceModule,
            'source_type' => $intent->sourceType,
            'source_number' => $intent->sourceNumber,
            'document_type' => $intent->documentType,
            'document_number' => $intent->documentNumber,
            'idempotency_key' => $intent->idempotencyKey,
        ] as $field => $value) {
            if (trim((string) $value) === '') {
                throw ValidationException::withMessages([$field => "Posting intent {$field} is required."]);
            }
        }

        if (! SourceType::tryFrom($intent->sourceType)) {
            throw ValidationException::withMessages(['source_type' => "Unsupported G/L source type {$intent->sourceType}."]);
        }

        $this->postingDateValidator->validate($intent->postingDate);
        $this->validateLinesBalance($intent);
        $this->validateCurrencyContract($intent);
        $this->validateAccounts($intent);
    }

    private function validateLinesBalance(PostingIntent $intent): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($intent->lines as $line) {
            if ($line->sourceType !== null && ! SourceType::tryFrom($line->sourceType)) {
                throw ValidationException::withMessages(['source_type' => "Unsupported G/L line source type {$line->sourceType}."]);
            }

            if (DecimalMath::isPositive($line->debitAmount) && DecimalMath::isPositive($line->creditAmount)) {
                throw ValidationException::withMessages(['lines' => 'A G/L line cannot contain both debit and credit amounts.']);
            }

            if (! DecimalMath::isPositive($line->debitAmount) && ! DecimalMath::isPositive($line->creditAmount)) {
                throw ValidationException::withMessages(['lines' => 'A G/L line must contain a debit or credit amount.']);
            }

            $totalDebit = DecimalMath::add($totalDebit, $line->debitAmount, DecimalPrecision::AMOUNT_SCALE);
            $totalCredit = DecimalMath::add($totalCredit, $line->creditAmount, DecimalPrecision::AMOUNT_SCALE);
        }

        if (DecimalMath::compare(DecimalMath::currency($totalDebit), DecimalMath::currency($totalCredit)) !== 0) {
            throw ValidationException::withMessages([
                'lines' => "Posting intent is not balanced. Debit {$totalDebit} does not equal credit {$totalCredit}.",
            ]);
        }
    }

    private function validateAccounts(PostingIntent $intent): void
    {
        $accountIds = collect($intent->lines)->map(fn (PostingIntentLine $line): int => $line->accountId)->unique()->values();

        $accounts = ChartOfAccount::query()
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        foreach ($intent->lines as $line) {
            $account = $accounts->get($line->accountId);

            if (! $account) {
                throw ValidationException::withMessages(['account_id' => "G/L account {$line->accountId} does not exist."]);
            }

            if (! $account->allowsDirectPosting()) {
                throw ValidationException::withMessages(['account_id' => "G/L account {$account->account_number} does not allow direct posting."]);
            }

            if ($account->isSystemControlled() && $intent->sourceType === SourceType::GENERAL_JOURNAL->value) {
                throw ValidationException::withMessages([
                    'account_id' => "G/L account {$account->account_number} is system controlled and cannot be posted through a manual journal.",
                ]);
            }

            if (
                $intent->businessId !== null
                && Schema::hasColumn($account->getTable(), 'business_id')
                && $account->business_id !== null
                && (int) $account->business_id !== $intent->businessId
            ) {
                throw ValidationException::withMessages(['business_id' => "G/L account {$account->account_number} belongs to another business."]);
            }
        }
    }

    /**
     * @param  array{code: string, factor: string}|null  $currencyContext
     */
    private function createGlEntry(
        PostingIntent $intent,
        PostingIntentLine $line,
        PostingTransaction $postingTransaction,
        int $transactionNumber,
        ?array $currencyContext = null,
    ): GlEntry {
        $debitAmount = DecimalMath::currency($line->debitAmount);
        $creditAmount = DecimalMath::currency($line->creditAmount);
        $amount = DecimalMath::currency(DecimalMath::sub($debitAmount, $creditAmount, DecimalPrecision::CURRENCY_SCALE));

        $documentDebitAmount = $currencyContext !== null && $line->documentDebitAmount !== null
            ? DecimalMath::amount($line->documentDebitAmount)
            : null;
        $documentCreditAmount = $currencyContext !== null && $line->documentCreditAmount !== null
            ? DecimalMath::amount($line->documentCreditAmount)
            : null;
        $documentAmount = ($documentDebitAmount !== null || $documentCreditAmount !== null)
            ? DecimalMath::sub($documentDebitAmount ?? '0', $documentCreditAmount ?? '0', DecimalPrecision::AMOUNT_SCALE)
            : null;

        return GlEntry::query()->create([
            'entry_number' => $this->sequenceAllocator->nextGlEntryNumber(),
            'transaction_number' => $transactionNumber,
            'posting_transaction_id' => $postingTransaction->id,
            'business_id' => $intent->businessId,
            'chart_of_account_id' => $line->accountId,
            'debit_amount' => $debitAmount,
            'debit_amount_lcy' => $debitAmount,
            'credit_amount' => $creditAmount,
            'credit_amount_lcy' => $creditAmount,
            'amount' => $amount,
            'amount_lcy' => $amount,
            // For CURRENCY_AWARE the normalized factor drives the G/L exchange
            // rate as well as currency_factor, so header, line rate and line
            // factor can never diverge (e.g. a blank NGN rate).
            'exchange_rate' => $currencyContext['factor'] ?? $intent->exchangeRate,
            'document_currency_code' => $currencyContext['code'] ?? null,
            'document_debit_amount' => $documentDebitAmount,
            'document_credit_amount' => $documentCreditAmount,
            'document_amount' => $documentAmount,
            'currency_factor' => $currencyContext['factor'] ?? null,
            'posting_line_type' => $currencyContext !== null ? $line->lineType?->value : null,
            'lcy_only_reason' => $currencyContext !== null ? $line->lcyOnlyReason?->value : null,
            'source_module' => $intent->sourceModule,
            'source_type' => $line->sourceType ?? $intent->sourceType,
            'source_id' => $intent->sourceId,
            'source_number' => $line->sourceNumber ?? $intent->sourceNumber,
            'document_type' => $intent->documentType,
            'document_number' => $intent->documentNumber,
            'external_document_number' => $intent->externalDocumentNumber,
            'idempotency_key' => $intent->idempotencyKey,
            'transaction_key' => $intent->transactionKey,
            'posting_group_source' => $line->postingGroupSource,
            'cost_component' => $line->costComponent,
            'document_date' => $intent->documentDate,
            'posting_date' => $intent->postingDate,
            'user_id' => $intent->actorId,
            'description' => $line->description ?? $intent->description,
            'dimensions' => array_replace($intent->dimensions, $line->dimensions),
            'item_ledger_entry_id' => $line->itemLedgerEntryId,
            'cust_ledger_entry_id' => $line->customerLedgerEntryId,
            'vendor_ledger_entry_id' => $line->vendorLedgerEntryId,
            'reversal_of_transaction_id' => $intent->reversalOfTransactionId,
            'shortcut_dimension_1_code' => $line->dimensions['shortcut_dimension_1_code'] ?? $intent->dimensions['shortcut_dimension_1_code'] ?? null,
            'shortcut_dimension_2_code' => $line->dimensions['shortcut_dimension_2_code'] ?? $intent->dimensions['shortcut_dimension_2_code'] ?? null,
        ]);
    }

    /**
     * Resolve the explicit currency-aware context, or null for LCY_ONLY.
     *
     * LCY_ONLY intents never reach this: the kernel performs no conversion and
     * no currency interpretation for them.
     *
     * @return array{code: string, factor: string}|null
     */
    private function currencyAwareContext(PostingIntent $intent): ?array
    {
        if (! $intent->mode->isCurrencyAware()) {
            return null;
        }

        try {
            $code = DocumentCurrency::normalizeCurrencyCode($intent->currencyCode);

            // NGN is well-defined and may resolve to 1 when omitted, but an
            // explicit non-1 NGN factor is rejected below. Foreign currencies
            // always require an explicit factor (never silently 1).
            $factor = $code === DocumentCurrency::LCY_CODE && trim((string) $intent->exchangeRate) === ''
                ? DecimalMath::toScale('1', 6)
                : DocumentCurrency::normalizeFactor($intent->exchangeRate);
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'currency_code' => 'A currency-aware posting requires a valid currency code and an explicit, finite, positive currency factor. '.$exception->getMessage(),
            ]);
        }

        if ($code === DocumentCurrency::LCY_CODE && DecimalMath::compare($factor, '1') !== 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'The local currency (NGN) must use a currency factor of 1 in a currency-aware posting.',
            ]);
        }

        return ['code' => $code, 'factor' => $factor];
    }

    private function validateCurrencyContract(PostingIntent $intent): void
    {
        $context = $this->currencyAwareContext($intent);

        if ($context === null) {
            return;
        }

        foreach ($intent->lines as $index => $line) {
            $this->validateCurrencyAwareLine($intent, $line, $context, (int) $index);
        }
    }

    /**
     * @param  array{code: string, factor: string}  $context
     */
    private function validateCurrencyAwareLine(PostingIntent $intent, PostingIntentLine $line, array $context, int $index): void
    {
        if ($line->lineType === null) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware line #{$index} must declare a line type ("
                    .PostingIntentLineType::DOCUMENT_MONETARY->value.' or '.PostingIntentLineType::LCY_ONLY->value.').',
            ]);
        }

        if ($line->lineType === PostingIntentLineType::DOCUMENT_MONETARY) {
            $this->validateDocumentMonetaryLine($line, $context, $index);

            return;
        }

        // LCY_ONLY: restricted escape hatch. Explicit allowlisted reason, and
        // never any document-currency amounts.
        if ($line->documentDebitAmount !== null || $line->documentCreditAmount !== null) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware line #{$index} is marked LCY_ONLY but carries document-currency amounts.",
            ]);
        }

        if ($line->lcyOnlyReason === null) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware LCY-only line #{$index} must declare an approved reason ("
                    .PostingLcyOnlyReason::ROUNDING->value.' or '.PostingLcyOnlyReason::VALUATION_ONLY->value.').',
            ]);
        }
    }

    /**
     * @param  array{code: string, factor: string}  $context
     */
    private function validateDocumentMonetaryLine(PostingIntentLine $line, array $context, int $index): void
    {
        if ($line->lcyOnlyReason !== null) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware document-monetary line #{$index} must not carry an LCY-only reason.",
            ]);
        }

        if ($line->documentDebitAmount === null || $line->documentCreditAmount === null) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware document-monetary line #{$index} must supply both a document debit and a document credit amount (use 0 for the unused side).",
            ]);
        }

        try {
            $documentDebit = DecimalMath::amount($line->documentDebitAmount);
            $documentCredit = DecimalMath::amount($line->documentCreditAmount);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware line #{$index} has a malformed document-currency amount.",
            ]);
        }

        if (DecimalMath::isPositive($documentDebit) && DecimalMath::isPositive($documentCredit)) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware line #{$index} cannot contain both a document debit and a document credit.",
            ]);
        }

        if (! DecimalMath::isPositive($documentDebit) && ! DecimalMath::isPositive($documentCredit)) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware document-monetary line #{$index} must contain a positive document debit or credit.",
            ]);
        }

        $documentNet = DecimalMath::sub($documentDebit, $documentCredit, DecimalPrecision::AMOUNT_SCALE);

        // Exact equality at persisted G/L scale (2 dp, HALF_UP). No tolerance:
        // a real rounding difference must be supplied by the caller as an
        // explicit ROUNDING line, never absorbed implicitly.
        $expectedLcy = DecimalMath::mul($documentNet, $context['factor'], DecimalPrecision::CURRENCY_SCALE);
        $actualLcy = DecimalMath::sub(
            DecimalMath::currency($line->debitAmount),
            DecimalMath::currency($line->creditAmount),
            DecimalPrecision::CURRENCY_SCALE,
        );

        if (DecimalMath::compare($actualLcy, $expectedLcy) !== 0) {
            throw ValidationException::withMessages([
                'lines' => "Currency-aware line #{$index} LCY economics ({$actualLcy}) do not reconcile with its document amount ({$documentNet} {$context['code']}) x factor {$context['factor']} = {$expectedLcy} at G/L scale; supply an explicit ROUNDING line for any rounding difference.",
            ]);
        }
    }

    /**
     * Deterministic fingerprint of currency-aware posting economics and
     * ownership.
     *
     * Covers the transaction identity that determines where a replay would
     * post (business, posting date, reversal) and, per line, every economic and
     * ownership field the DTO actually carries: account, classification and
     * LCY-only reason, LCY and document economics, canonicalised dimensions,
     * posting group / cost component, item/location/work-center ownership,
     * subledger (customer/vendor) and item-ledger ownership, and source trace.
     *
     * Presentation-only text (descriptions) is intentionally excluded. Line
     * order is canonicalised by sorting so a harmless reorder does not create a
     * false conflict, while duplicate-line multiplicity is preserved.
     *
     * @param  array{code: string, factor: string}  $context
     */
    private function economicFingerprint(PostingIntent $intent, array $context): string
    {
        $lines = [];

        foreach ($intent->lines as $line) {
            $lines[] = json_encode([
                'account_id' => $line->accountId,
                'line_type' => $line->lineType?->value,
                'lcy_only_reason' => $line->lcyOnlyReason?->value,
                'lcy_debit' => DecimalMath::currency($line->debitAmount),
                'lcy_credit' => DecimalMath::currency($line->creditAmount),
                'document_debit' => $line->documentDebitAmount !== null ? DecimalMath::amount($line->documentDebitAmount) : null,
                'document_credit' => $line->documentCreditAmount !== null ? DecimalMath::amount($line->documentCreditAmount) : null,
                'dimensions' => self::canonicalizeDimensions($line->dimensions),
                'posting_group_source' => $line->postingGroupSource,
                'cost_component' => $line->costComponent,
                'item_id' => $line->itemId,
                'location_id' => $line->locationId,
                'work_center_id' => $line->workCenterId,
                'machine_center_id' => $line->machineCenterId,
                'item_ledger_entry_id' => $line->itemLedgerEntryId,
                'customer_ledger_entry_id' => $line->customerLedgerEntryId,
                'vendor_ledger_entry_id' => $line->vendorLedgerEntryId,
                'source_type' => $line->sourceType,
                'source_number' => $line->sourceNumber,
            ], JSON_THROW_ON_ERROR);
        }

        sort($lines, SORT_STRING);

        return hash('sha256', json_encode([
            'business_id' => $intent->businessId,
            'posting_date' => $intent->postingDate->toDateString(),
            'mode' => $intent->mode->value,
            'currency_code' => $context['code'],
            'currency_factor' => $context['factor'],
            'reversal_of_transaction_id' => $intent->reversalOfTransactionId,
            'lines' => $lines,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Canonicalise a dimensions map so key ordering never affects the
     * fingerprint while distinct values always do. Nested maps are handled
     * recursively.
     *
     * @param  array<array-key, mixed>  $dimensions
     * @return array<string, mixed>
     */
    private static function canonicalizeDimensions(array $dimensions): array
    {
        $canonical = [];

        foreach ($dimensions as $key => $value) {
            $canonical[(string) $key] = is_array($value)
                ? self::canonicalizeDimensions($value)
                : $value;
        }

        ksort($canonical);

        return $canonical;
    }

    /**
     * Fail closed when an idempotency key is replayed with different economics.
     *
     * LCY_ONLY replays preserve the historical contract (return the existing
     * transaction). Currency-aware replays must match the stored fingerprint
     * exactly; a currency-aware posting can never be silently reused as an
     * LCY_ONLY intent, and vice versa.
     */
    private function assertIdempotentReplayMatches(PostingIntent $intent, PostingTransaction $existing): void
    {
        $existingFingerprint = $existing->economic_fingerprint;

        if ($intent->mode->isCurrencyAware()) {
            try {
                $context = $this->currencyAwareContext($intent);
                $incomingFingerprint = $this->economicFingerprint($intent, $context);
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Idempotency key '.$intent->idempotencyKey.' could not be compared against its existing posting: '.$exception->getMessage(),
                ]);
            }

            if ($existingFingerprint === null || ! hash_equals($existingFingerprint, $incomingFingerprint)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'Idempotency key '.$intent->idempotencyKey.' already belongs to a posting with different currency-aware economics; refusing to reuse it.',
                ]);
            }

            return;
        }

        if ($existingFingerprint !== null) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Idempotency key '.$intent->idempotencyKey.' already belongs to a currency-aware posting; refusing to reuse it for an LCY-only intent.',
            ]);
        }
    }
}
