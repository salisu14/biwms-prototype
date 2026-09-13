<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\BusinessException;
use App\Models\GlEntry;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;

final class VendorLedgerHistoricalNormalizationService
{
    private const NORMALIZATION_VERSION = 'vendor-ledger-historical-normalization-v1';

    /**
     * Every protected before-value the caller must assert. The capability
     * refuses to act unless the persisted row matches all of them.
     *
     * @var array<int, string>
     */
    private const REQUIRED_BEFORE_KEYS = [
        'vendor_id',
        'business_id',
        'document_type',
        'document_number',
        'source_id',
        'source_type',
        'debit_amount',
        'credit_amount',
        'amount',
        'remaining_amount',
        'open',
        'fully_applied',
        'reversed',
        'original_debit_amount',
        'original_credit_amount',
    ];

    /**
     * Before-values compared with decimal semantics rather than strict equality.
     *
     * @var array<int, string>
     */
    private const DECIMAL_BEFORE_KEYS = [
        'debit_amount',
        'credit_amount',
        'amount',
        'remaining_amount',
        'original_debit_amount',
        'original_credit_amount',
    ];

    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * Normalize a verified malformed historical purchase-invoice vendor ledger
     * fact to the canonical credit-side payable representation.
     *
     * This is the only sanctioned capability that rewrites protected posted
     * vendor ledger facts. It fails closed unless the persisted row matches the
     * caller's explicit expected before-state and the known malformed
     * historical signature. The corrected representation is derived internally
     * from the persisted exposure, so callers cannot supply arbitrary
     * after-values (account, amount, party, document, or linkage).
     *
     * @param  array<string, mixed>  $expectedBefore
     */
    public function normalizeMalformedPurchaseInvoice(
        int $vendorLedgerEntryId,
        array $expectedBefore,
        int $payablesGlEntryId,
        string $incidentIdentifier,
        string $reason,
        ?int $actorId = null,
    ): VendorLedgerEntry {
        return DB::transaction(function () use ($vendorLedgerEntryId, $expectedBefore, $payablesGlEntryId, $incidentIdentifier, $reason, $actorId): VendorLedgerEntry {
            $entry = VendorLedgerEntry::query()
                ->lockForUpdate()
                ->findOrFail($vendorLedgerEntryId);

            $this->assertUniqueInvoiceLedgerEntry($entry);

            if ($this->isCanonicalInvoiceRepresentation($entry, $payablesGlEntryId)) {
                return $entry;
            }

            $this->assertExpectedBefore($entry, $expectedBefore);
            $this->assertMalformedInvoiceRepresentation($entry);
            $this->assertPayablesGlEntry($entry, $payablesGlEntryId);

            $before = $this->snapshot($entry);

            VendorLedgerEntry::query()
                ->whereKey($entry->getKey())
                ->update($this->canonicalAttributes($entry, $payablesGlEntryId));

            $entry->refresh();

            $this->auditTrailService->recordGeneric(
                eventType: 'subledger_correction',
                action: 'vendor_ledger_historical_normalization_applied',
                auditable: $entry,
                documentType: (string) $entry->document_type,
                documentNo: (string) $entry->document_number,
                userId: $actorId,
                description: 'Normalized malformed historical purchase invoice vendor ledger representation for '.$entry->document_number,
                oldValues: $before,
                newValues: $this->snapshot($entry),
                metadata: [
                    'incident_identifier' => $incidentIdentifier,
                    'vendor_ledger_entry_id' => $entry->getKey(),
                    'vendor_id' => (int) $entry->vendor_id,
                    'business_id' => (int) $entry->business_id,
                    'payables_gl_entry_id' => $payablesGlEntryId,
                    'reason' => $reason,
                    'normalization_version' => self::NORMALIZATION_VERSION,
                ],
            );

            return $entry;
        });
    }

    private function assertUniqueInvoiceLedgerEntry(VendorLedgerEntry $entry): void
    {
        if ((string) $entry->document_type !== 'PURCHASE_INVOICE') {
            throw new BusinessException('Only a historical purchase-invoice vendor ledger entry can be normalized.');
        }

        $matching = VendorLedgerEntry::query()
            ->where('vendor_id', $entry->vendor_id)
            ->where('business_id', $entry->business_id)
            ->where('document_type', $entry->document_type)
            ->where('document_number', $entry->document_number)
            ->count();

        if ($matching !== 1) {
            throw new BusinessException('The historical vendor ledger entry is not uniquely identifiable; found '.$matching.' matching rows, so no normalization is safe.');
        }
    }

    /**
     * @param  array<string, mixed>  $expectedBefore
     */
    private function assertExpectedBefore(VendorLedgerEntry $entry, array $expectedBefore): void
    {
        foreach (self::REQUIRED_BEFORE_KEYS as $key) {
            if (! array_key_exists($key, $expectedBefore)) {
                throw new BusinessException("Historical vendor ledger normalization requires the expected before-value '{$key}'.");
            }
        }

        if ((int) $entry->vendor_id !== (int) $expectedBefore['vendor_id']
            || (int) $entry->business_id !== (int) $expectedBefore['business_id']
            || (string) $entry->document_type !== (string) $expectedBefore['document_type']
            || (string) $entry->document_number !== (string) $expectedBefore['document_number']
            || (int) $entry->source_id !== (int) $expectedBefore['source_id']
            || (string) $entry->source_type !== (string) $expectedBefore['source_type']
            || (bool) $entry->open !== (bool) $expectedBefore['open']
            || (bool) $entry->fully_applied !== (bool) $expectedBefore['fully_applied']
            || (bool) $entry->reversed !== (bool) $expectedBefore['reversed']) {
            throw new BusinessException('The historical vendor ledger entry does not match the expected before-state; refusing to normalize.');
        }

        foreach (self::DECIMAL_BEFORE_KEYS as $key) {
            if (DecimalMath::compare($entry->{$key}, $expectedBefore[$key]) !== 0) {
                throw new BusinessException("The historical vendor ledger entry {$key} does not match the expected before-state; refusing to normalize.");
            }
        }
    }

    private function assertMalformedInvoiceRepresentation(VendorLedgerEntry $entry): void
    {
        $isMalformed = ! (bool) $entry->reversed
            && DecimalMath::compare($entry->credit_amount, '0') === 0
            && DecimalMath::isPositive($entry->debit_amount)
            && DecimalMath::isPositive($entry->amount)
            && DecimalMath::compare($entry->amount, $entry->debit_amount) === 0
            && DecimalMath::compare($entry->remaining_amount, $entry->amount) === 0
            && (bool) $entry->open
            && ! (bool) $entry->fully_applied
            && DecimalMath::isPositive($entry->original_debit_amount)
            && DecimalMath::compare($entry->original_credit_amount, '0') === 0;

        if (! $isMalformed) {
            throw new BusinessException('The vendor ledger entry does not match the known malformed historical purchase-invoice representation; refusing to normalize.');
        }
    }

    private function assertPayablesGlEntry(VendorLedgerEntry $entry, int $payablesGlEntryId): void
    {
        $glEntry = GlEntry::query()->find($payablesGlEntryId);

        if (! $glEntry) {
            throw new BusinessException('The payables G/L control entry for the historical vendor ledger normalization does not exist.');
        }

        $payablesAccountId = VendorPostingGroup::query()
            ->whereKey($entry->vendor_posting_group_id)
            ->value('payables_account_id');

        if (! $payablesAccountId) {
            throw new BusinessException('The vendor posting group payables account is not configured; refusing to normalize.');
        }

        $matches = (int) $glEntry->business_id === (int) $entry->business_id
            && (string) $glEntry->document_type === (string) $entry->document_type
            && (string) $glEntry->document_number === (string) $entry->document_number
            && (int) $glEntry->chart_of_account_id === (int) $payablesAccountId
            && $glEntry->posting_transaction_id !== null
            && $glEntry->reversal_of_gl_entry_id === null
            && DecimalMath::isPositive($glEntry->credit_amount)
            && DecimalMath::compare($glEntry->debit_amount, '0') === 0;

        if (! $matches) {
            throw new BusinessException('The supplied payables G/L control entry does not match the historical vendor ledger entry; refusing to normalize.');
        }
    }

    private function isCanonicalInvoiceRepresentation(VendorLedgerEntry $entry, int $payablesGlEntryId): bool
    {
        $exposure = DecimalMath::currency($entry->amount);

        return ! (bool) $entry->reversed
            && DecimalMath::compare($entry->debit_amount, '0') === 0
            && DecimalMath::isPositive($entry->credit_amount)
            && DecimalMath::compare($entry->credit_amount, $exposure) === 0
            && DecimalMath::compare($entry->remaining_amount, $exposure) === 0
            && (bool) $entry->open
            && ! (bool) $entry->fully_applied
            && DecimalMath::compare($entry->original_debit_amount, '0') === 0
            && DecimalMath::compare($entry->original_credit_amount, $this->originalCreditFor($entry, $exposure)) === 0
            && (int) $entry->gl_entry_id === $payablesGlEntryId;
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalAttributes(VendorLedgerEntry $entry, int $payablesGlEntryId): array
    {
        $exposure = DecimalMath::currency($entry->amount);

        return [
            'debit_amount' => '0',
            'credit_amount' => $exposure,
            'amount' => $exposure,
            'remaining_amount' => $exposure,
            'open' => true,
            'fully_applied' => false,
            'original_debit_amount' => '0',
            'original_credit_amount' => $this->originalCreditFor($entry, $exposure),
            'gl_entry_id' => $payablesGlEntryId,
            'updated_at' => now(),
        ];
    }

    private function originalCreditFor(VendorLedgerEntry $entry, string $exposure): string
    {
        return DecimalMath::currency(DecimalMath::div(
            $exposure,
            $entry->currency_factor ?: '1',
            DecimalPrecision::CURRENCY_SCALE
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(VendorLedgerEntry $entry): array
    {
        return [
            'vendor_id' => (int) $entry->vendor_id,
            'business_id' => (int) $entry->business_id,
            'document_type' => (string) $entry->document_type,
            'document_number' => (string) $entry->document_number,
            'source_id' => $entry->source_id !== null ? (int) $entry->source_id : null,
            'source_type' => (string) $entry->source_type,
            'debit_amount' => DecimalMath::currency($entry->debit_amount),
            'credit_amount' => DecimalMath::currency($entry->credit_amount),
            'amount' => DecimalMath::currency($entry->amount),
            'remaining_amount' => DecimalMath::currency($entry->remaining_amount),
            'running_balance' => DecimalMath::currency($entry->running_balance),
            'open' => (bool) $entry->open,
            'fully_applied' => (bool) $entry->fully_applied,
            'reversed' => (bool) $entry->reversed,
            'original_debit_amount' => DecimalMath::currency($entry->original_debit_amount),
            'original_credit_amount' => DecimalMath::currency($entry->original_credit_amount),
            'gl_entry_id' => $entry->gl_entry_id !== null ? (int) $entry->gl_entry_id : null,
        ];
    }
}
