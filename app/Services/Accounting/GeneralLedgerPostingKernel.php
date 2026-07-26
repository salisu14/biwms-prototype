<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\PostingIntent;
use App\Accounting\PostingIntentLine;
use App\Enums\SourceType;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Models\PostingTransaction;
use App\Services\AuditTrailService;
use App\Services\PostingDateValidator;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
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
                return $existing->load('glEntries');
            }

            $this->validateIntent($intent);

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
                'currency_code' => $intent->currencyCode,
                'exchange_rate' => $intent->exchangeRate,
                'dimensions' => $intent->dimensions,
                'status' => 'completed',
                'actor_id' => $intent->actorId,
                'reversal_of_transaction_id' => $intent->reversalOfTransactionId,
                'reason' => $intent->reason,
                'description' => $intent->description,
            ]);

            foreach ($intent->lines as $line) {
                $this->createGlEntry($intent, $line, $postingTransaction, $transactionNumber);
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

    private function createGlEntry(
        PostingIntent $intent,
        PostingIntentLine $line,
        PostingTransaction $postingTransaction,
        int $transactionNumber,
    ): GlEntry {
        $debitAmount = DecimalMath::currency($line->debitAmount);
        $creditAmount = DecimalMath::currency($line->creditAmount);
        $amount = DecimalMath::currency(DecimalMath::sub($debitAmount, $creditAmount, DecimalPrecision::CURRENCY_SCALE));

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
            'exchange_rate' => $intent->exchangeRate,
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
}
