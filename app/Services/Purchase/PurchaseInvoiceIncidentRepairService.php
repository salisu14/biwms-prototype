<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Models\ItemLedgerEntry;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostingTransaction;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\ValueEntry;
use App\Models\VendorLedgerEntry;
use App\Services\AuditTrailService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Finance\VendorLedgerHistoricalNormalizationService;
use App\Services\PostingDateValidator;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class PurchaseInvoiceIncidentRepairService
{
    private const EXPECTED_INVOICE_TOTAL = '262866.20';

    private const EXPECTED_INVOICE_ID = 3;

    private const EXPECTED_INVOICE_NUMBER = 'PI-2026-00001';

    private const EXPECTED_BUSINESS_ID = 1;

    private const EXPECTED_VENDOR_ID = 2;

    private const EXPECTED_CURRENCY_CODE = 'USD';

    private const EXPECTED_POSTING_DATE = '2026-09-11';

    private const EXPECTED_ORDER_ID = 2;

    private const EXPECTED_ORDER_NUMBER = 'PO-2026-00002';

    private const EXPECTED_GRNI_ACCOUNT_NUMBER = '20200';

    private const EXPECTED_PAYABLES_ACCOUNT_NUMBER = '31202';

    private const REPAIR_VERSION = 'purchase-invoice-incident-repair-v1';

    /**
     * @var array<int, array{entry_number: int, item_code: string, amount: string}>
     */
    private const RECEIPT_ILE_EXPECTATIONS = [
        ['entry_number' => 50, 'item_code' => '2100', 'amount' => '50000.00'],
        ['entry_number' => 51, 'item_code' => '2200', 'amount' => '212866.20'],
    ];

    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
        private readonly PostingDateValidator $postingDateValidator,
        private readonly AuditTrailService $auditTrailService,
        private readonly VendorLedgerHistoricalNormalizationService $vendorLedgerNormalizationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function analyze(string $invoiceNumber = 'PI-2026-00001', string $orderNumber = 'PO-2026-00002', int $businessId = 1): array
    {
        $this->assertIncidentScope($invoiceNumber, $orderNumber, $businessId);

        return $this->inspect($invoiceNumber, $orderNumber, $businessId, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        string $invoiceNumber = 'PI-2026-00001',
        string $orderNumber = 'PO-2026-00002',
        int $businessId = 1,
        ?int $actorId = null,
        bool $simulateFailureAfterGlCorrection = false,
    ): array {
        $this->assertIncidentScope($invoiceNumber, $orderNumber, $businessId);

        return DB::transaction(function () use ($invoiceNumber, $orderNumber, $businessId, $actorId, $simulateFailureAfterGlCorrection): array {
            $inspection = $this->inspect($invoiceNumber, $orderNumber, $businessId, true);

            if ($inspection['already_repaired']) {
                return [...$inspection, 'executed' => false, 'idempotent' => true];
            }

            $this->postingDateValidator->validate($inspection['posting_date']);

            $before = $this->repairableBeforeState($inspection);

            $neutralizerEntries = [];
            foreach ($inspection['gl']['malformed_entries'] as $entry) {
                $neutralizerEntries[] = $this->createNeutralizerGlEntry($entry, $inspection, $actorId);
            }

            if ($simulateFailureAfterGlCorrection) {
                throw new BusinessException('Simulated repair failure after G/L neutralization.');
            }

            $replacementTransaction = $this->postReplacementLiabilityTransaction($inspection, $actorId);

            $replacementPayablesEntry = $replacementTransaction->glEntries
                ->firstWhere('chart_of_account_id', $inspection['payables_account_id']);

            if (! $replacementPayablesEntry) {
                throw new BusinessException('The replacement liability transaction did not create the expected payables G/L entry.');
            }

            $this->repairVendorLedgerEntry($inspection, (int) $replacementPayablesEntry->id, $actorId);
            $this->repairItemLedgerEntries($inspection);

            $after = $this->inspect($invoiceNumber, $orderNumber, $businessId, true);

            $this->auditTrailService->recordGeneric(
                eventType: 'purchase_invoice_incident_repair',
                action: 'purchase_invoice_incident_repaired',
                auditable: $inspection['invoice'],
                documentType: 'PURCHASE_INVOICE',
                documentNo: $invoiceNumber,
                userId: $actorId,
                description: 'Controlled local repair for historical purchase invoice incident '.$invoiceNumber,
                oldValues: $before,
                newValues: $this->repairableAfterState($after),
                metadata: [
                    'repair_version' => self::REPAIR_VERSION,
                    'incident_identifier' => $this->incidentIdentifier($invoiceNumber, $orderNumber, $businessId),
                    'business_id' => $businessId,
                    'purchase_order_number' => $orderNumber,
                    'replacement_posting_transaction_id' => $replacementTransaction->id,
                    'replacement_transaction_number' => $replacementTransaction->transaction_number,
                    'neutralizer_gl_entry_ids' => collect($neutralizerEntries)->pluck('id')->all(),
                    'neutralized_original_gl_entry_ids' => collect($inspection['gl']['malformed_entries'])->pluck('id')->all(),
                    'records_not_touched' => $inspection['records_not_touched'],
                ],
            );

            return [
                ...$after,
                'executed' => true,
                'idempotent' => false,
                'neutralizer_gl_entry_ids' => collect($neutralizerEntries)->pluck('id')->all(),
                'replacement_posting_transaction_id' => $replacementTransaction->id,
            ];
        });
    }

    private function assertIncidentScope(string $invoiceNumber, string $orderNumber, int $businessId): void
    {
        if ($invoiceNumber !== 'PI-2026-00001' || $orderNumber !== 'PO-2026-00002' || $businessId !== 1) {
            throw new BusinessException('This repair service is intentionally scoped only to PI-2026-00001 / PO-2026-00002 / business_id=1.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(string $invoiceNumber, string $orderNumber, int $businessId, bool $forExecution): array
    {
        $invoice = $this->one(
            PurchaseInvoice::query()
                ->with(['vendor.vendorPostingGroup.payablesAccount', 'lines.item', 'purchaseOrder'])
                ->when($forExecution, fn ($query) => $query->lockForUpdate())
                ->where('document_number', $invoiceNumber)
                ->where('business_id', $businessId),
            'Purchase invoice'
        );

        $order = $this->one(
            PurchaseOrder::query()
                ->with(['lines.item'])
                ->when($forExecution, fn ($query) => $query->lockForUpdate())
                ->where('order_number', $orderNumber)
                ->where('business_id', $businessId),
            'Purchase order'
        );

        $postedInvoice = $this->one(
            PostedPurchaseInvoice::query()
                ->when($forExecution, fn ($query) => $query->lockForUpdate())
                ->where('document_number', $invoiceNumber)
                ->where('order_id', $order->id)
                ->where('business_id', $businessId),
            'Posted purchase invoice'
        );

        $this->assertDocumentShape($invoice, $order, $postedInvoice, $invoiceNumber, $orderNumber, $businessId);

        $payablesAccount = $invoice->vendor?->getPayablesAccount();
        if (! $payablesAccount) {
            throw new BusinessException('The vendor payables account is not configured; no incident repair is safe.');
        }

        if ((string) $payablesAccount->account_number !== self::EXPECTED_PAYABLES_ACCOUNT_NUMBER) {
            throw new BusinessException('The configured vendor payables account does not match the known incident payables account.');
        }

        $ilePlans = $this->receiptItemLedgerPlans($invoiceNumber, $orderNumber, $businessId, $forExecution);
        $vendorLedgerEntry = $this->vendorLedgerEntry($invoice, $forExecution);
        $replacementTransaction = PostingTransaction::query()
            ->where('idempotency_key', $this->replacementTransactionKey($invoiceNumber))
            ->first();

        $glPlan = $this->glPlan($invoiceNumber, $businessId, (int) $payablesAccount->id, $replacementTransaction !== null);
        $vendorPlan = $this->vendorPlan($vendorLedgerEntry, $postedInvoice);
        $alreadyRepaired = $this->isAlreadyRepaired($replacementTransaction, $glPlan, $vendorPlan, $ilePlans);

        if (! $alreadyRepaired) {
            $this->assertRepairableState($glPlan, $vendorPlan, $ilePlans, $replacementTransaction);
        }

        return [
            'repair_version' => self::REPAIR_VERSION,
            'incident_identifier' => $this->incidentIdentifier($invoiceNumber, $orderNumber, $businessId),
            'invoice' => $invoice,
            'purchase_order' => $order,
            'posted_invoice' => $postedInvoice,
            'business_id' => $businessId,
            'invoice_number' => $invoiceNumber,
            'purchase_order_number' => $orderNumber,
            'invoice_id' => $invoice->id,
            'purchase_order_id' => $order->id,
            'posted_purchase_invoice_id' => $postedInvoice->id,
            'vendor_id' => $invoice->vendor_id,
            'payables_account_id' => (int) $payablesAccount->id,
            'posting_date' => $invoice->posting_date,
            'document_date' => $invoice->document_date,
            'currency_code' => $invoice->currency_code ?: 'NGN',
            'currency_factor' => $invoice->currency_factor ?: '1',
            'amount' => DecimalMath::currency($invoice->grand_total),
            'gl' => $glPlan,
            'vendor_ledger' => $vendorPlan,
            'item_ledger_entries' => $ilePlans,
            'replacement_transaction_key' => $this->replacementTransactionKey($invoiceNumber),
            'replacement_transaction_id' => $replacementTransaction?->id,
            'already_repaired' => $alreadyRepaired,
            'execution_allowed' => ! $alreadyRepaired,
            'records_not_touched' => [
                'purchase_invoice_row',
                'posted_purchase_invoice_row',
                'purchase_order_row',
                'purchase_order_lines',
                'purchase_invoice_lines',
                'posted_purchase_invoice_lines',
                'purchase_receipt_item_ledger_quantities',
                'value_entries',
                'item_stock_quantity',
                'number_series',
                'payments',
                'purchase_credit_memos',
            ],
        ];
    }

    private function assertDocumentShape(PurchaseInvoice $invoice, PurchaseOrder $order, PostedPurchaseInvoice $postedInvoice, string $invoiceNumber, string $orderNumber, int $businessId): void
    {
        if ((int) $invoice->getKey() !== self::EXPECTED_INVOICE_ID) {
            throw new BusinessException('The purchase invoice id does not match the known incident.');
        }

        if ((string) $invoice->document_number !== self::EXPECTED_INVOICE_NUMBER
            || (string) $invoice->document_number !== $invoiceNumber) {
            throw new BusinessException('The purchase invoice number does not match the known incident.');
        }

        if ((int) $invoice->business_id !== self::EXPECTED_BUSINESS_ID || $businessId !== self::EXPECTED_BUSINESS_ID) {
            throw new BusinessException('The purchase invoice business does not match the known incident.');
        }

        if ((int) $invoice->vendor_id !== self::EXPECTED_VENDOR_ID) {
            throw new BusinessException('The purchase invoice vendor does not match the known incident.');
        }

        if (strtoupper(trim((string) $invoice->currency_code)) !== self::EXPECTED_CURRENCY_CODE) {
            throw new BusinessException('The purchase invoice currency does not match the known incident.');
        }

        if ($invoice->posting_date?->toDateString() !== self::EXPECTED_POSTING_DATE) {
            throw new BusinessException('The purchase invoice posting date does not match the known incident.');
        }

        if ((int) $order->getKey() !== self::EXPECTED_ORDER_ID) {
            throw new BusinessException('The purchase order id does not match the known incident.');
        }

        if ((string) $order->order_number !== self::EXPECTED_ORDER_NUMBER
            || (string) $order->order_number !== $orderNumber) {
            throw new BusinessException('The purchase order number does not match the known incident.');
        }

        if ((int) $order->business_id !== self::EXPECTED_BUSINESS_ID) {
            throw new BusinessException('The purchase order business does not match the known incident.');
        }

        if ((int) $order->vendor_id !== self::EXPECTED_VENDOR_ID) {
            throw new BusinessException('The purchase order vendor does not match the known incident.');
        }

        if ($invoice->status !== ApprovalStatus::POSTED) {
            throw new BusinessException("Purchase invoice {$invoiceNumber} is not posted.");
        }

        if ($order->status !== PurchaseOrderStatus::CLOSED) {
            throw new BusinessException("Purchase order {$orderNumber} is not closed.");
        }

        if ((int) $order->vendor_id !== (int) $invoice->vendor_id) {
            throw new BusinessException('The purchase order vendor does not match the purchase invoice vendor.');
        }

        if ((int) $invoice->order_id !== (int) $order->id || (string) $invoice->order_number !== $orderNumber) {
            throw new BusinessException('The purchase invoice is not linked to the expected purchase order.');
        }

        if ((int) $postedInvoice->vendor_id !== (int) $invoice->vendor_id) {
            throw new BusinessException('The posted purchase invoice vendor does not match the purchase invoice.');
        }

        foreach ([$invoice, $order, $postedInvoice] as $model) {
            if ((int) $model->business_id !== $businessId) {
                throw new BusinessException('The incident document business ownership does not match the requested business.');
            }
        }

        if (DecimalMath::compare($invoice->grand_total, self::EXPECTED_INVOICE_TOTAL) !== 0
            || DecimalMath::compare($postedInvoice->grand_total, self::EXPECTED_INVOICE_TOTAL) !== 0) {
            throw new BusinessException('The purchase invoice total does not match the known incident amount.');
        }

        if ($invoice->vendor_id === null || $invoice->lines->count() !== 2 || $postedInvoice->lines()->count() !== 2) {
            throw new BusinessException('The purchase invoice line/vendor shape does not match the known incident.');
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function receiptItemLedgerPlans(string $invoiceNumber, string $orderNumber, int $businessId, bool $forExecution): array
    {
        return collect(self::RECEIPT_ILE_EXPECTATIONS)
            ->map(function (array $expected) use ($invoiceNumber, $orderNumber, $businessId, $forExecution): array {
                $entry = $this->one(
                    ItemLedgerEntry::query()
                        ->with('item')
                        ->when($forExecution, fn ($query) => $query->lockForUpdate())
                        ->where('entry_number', $expected['entry_number'])
                        ->where('document_type', 'PURCHASE_RECEIPT')
                        ->where('document_number', $orderNumber)
                        ->where('business_id', $businessId),
                    'Receipt item ledger entry '.$expected['entry_number']
                );

                if (($entry->item?->item_code ?? null) !== $expected['item_code']) {
                    throw new BusinessException("Receipt item ledger entry {$expected['entry_number']} does not belong to item {$expected['item_code']}.");
                }

                $valueEntries = ValueEntry::query()
                    ->when($forExecution, fn ($query) => $query->lockForUpdate())
                    ->where('item_ledger_entry_no', $entry->entry_number)
                    ->where('document_type', 'PURCHASE_INVOICE')
                    ->where('document_no', $invoiceNumber)
                    ->where('value_entry_state', 'actual')
                    ->where(function ($query): void {
                        $query->where('expected_cost', false)->orWhereNull('expected_cost');
                    })
                    ->get();

                if ($valueEntries->count() !== 1) {
                    throw new BusinessException("Receipt item ledger entry {$expected['entry_number']} does not have exactly one actual value entry for {$invoiceNumber}.");
                }

                $actualCost = DecimalMath::currency($valueEntries->sum('cost_amount_actual'));
                if (DecimalMath::compare($actualCost, $expected['amount']) !== 0) {
                    throw new BusinessException("Actual Value Entry cost for ILE {$expected['entry_number']} does not match the known incident amount.");
                }

                $expectedRemainder = DecimalMath::amount(DecimalMath::sub(
                    $entry->cost_amount_expected,
                    $actualCost,
                    DecimalPrecision::AMOUNT_SCALE
                ));
                $canonicalExpected = DecimalMath::isPositive($expectedRemainder) ? $expectedRemainder : '0';

                return [
                    'item_ledger_entry_id' => $entry->id,
                    'entry_number' => (int) $entry->entry_number,
                    'item_code' => $entry->item?->item_code,
                    'quantity' => DecimalMath::quantity($entry->quantity),
                    'current_cost_amount_actual' => DecimalMath::currency($entry->cost_amount_actual),
                    'current_cost_amount_expected' => DecimalMath::currency($entry->cost_amount_expected),
                    'current_purchase_amount_actual' => DecimalMath::currency($entry->purchase_amount_actual),
                    'authoritative_value_entry_cost' => $actualCost,
                    'canonical_cost_amount_expected' => $canonicalExpected,
                    'expected_cost_precondition_met' => DecimalMath::isZero($canonicalExpected)
                        && DecimalMath::isZero($entry->cost_amount_actual)
                        && DecimalMath::isZero($entry->purchase_amount_actual),
                    'value_entry_ids' => $valueEntries->pluck('id')->all(),
                    'already_synced' => DecimalMath::compare($entry->cost_amount_actual, $actualCost) === 0
                        && DecimalMath::compare($entry->purchase_amount_actual, $actualCost) === 0
                        && DecimalMath::compare($entry->cost_amount_expected, '0') === 0,
                ];
            })
            ->all();
    }

    private function vendorLedgerEntry(PurchaseInvoice $invoice, bool $forExecution): VendorLedgerEntry
    {
        return $this->one(
            VendorLedgerEntry::query()
                ->when($forExecution, fn ($query) => $query->lockForUpdate())
                ->where('document_type', 'PURCHASE_INVOICE')
                ->where('document_number', $invoice->document_number)
                ->where('vendor_id', $invoice->vendor_id),
            'Vendor ledger entry'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function glPlan(string $invoiceNumber, int $businessId, int $payablesAccountId, bool $replacementExists): array
    {
        $scopedEntries = GlEntry::query()
            ->where('document_type', 'PURCHASE_INVOICE')
            ->where('document_number', $invoiceNumber)
            ->whereIn('transaction_number', [101, 103, 104]);

        $malformedEntries = (clone $scopedEntries)
            ->whereNull('reversal_of_gl_entry_id')
            ->orderBy('transaction_number')
            ->orderBy('id')
            ->get();

        $neutralizers = (clone $scopedEntries)
            ->whereNotNull('reversal_of_gl_entry_id')
            ->get();

        $byTransaction = $malformedEntries->groupBy('transaction_number');
        $debitEntries = $malformedEntries->filter(fn (GlEntry $entry): bool => DecimalMath::isPositive($entry->debit_amount))->values();
        $creditEntries = $malformedEntries->filter(fn (GlEntry $entry): bool => DecimalMath::isPositive($entry->credit_amount))->values();
        $grniAccountIds = $debitEntries->pluck('chart_of_account_id')->unique()->values();
        $grniAccount = $grniAccountIds->count() === 1
            ? ChartOfAccount::query()->find($grniAccountIds->first())
            : null;

        $replacement = PostingTransaction::query()
            ->where('idempotency_key', $this->replacementTransactionKey($invoiceNumber))
            ->with('glEntries')
            ->first();

        return [
            'malformed_entries' => $malformedEntries,
            'malformed_summary' => $malformedEntries->map(fn (GlEntry $entry): array => [
                'id' => $entry->id,
                'transaction_number' => $entry->transaction_number,
                'account_id' => $entry->chart_of_account_id,
                'debit' => DecimalMath::currency($entry->debit_amount),
                'credit' => DecimalMath::currency($entry->credit_amount),
            ])->all(),
            'neutralizer_entries' => $neutralizers,
            'neutralizer_entry_ids' => $neutralizers->pluck('id')->all(),
            'grni_account_id' => $grniAccountIds->first(),
            'grni_account_number' => $grniAccount?->account_number,
            'payables_account_id' => $payablesAccountId,
            'replacement_exists' => $replacementExists,
            'replacement_transaction_id' => $replacement?->id,
            'replacement_transaction_number' => $replacement?->transaction_number,
            'malformed_group_count' => $byTransaction->count(),
            'malformed_entry_count' => $malformedEntries->count(),
            'debit_total' => DecimalMath::currency($debitEntries->sum('debit_amount')),
            'credit_total' => DecimalMath::currency($creditEntries->sum('credit_amount')),
            'grni_account_count' => $grniAccountIds->count(),
            'credit_payables_count' => $creditEntries->where('chart_of_account_id', $payablesAccountId)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vendorPlan(VendorLedgerEntry $entry, PostedPurchaseInvoice $postedInvoice): array
    {
        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'current' => [
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
                'open' => (bool) $entry->open,
                'fully_applied' => (bool) $entry->fully_applied,
                'reversed' => (bool) $entry->reversed,
                'original_debit_amount' => DecimalMath::currency($entry->original_debit_amount),
                'original_credit_amount' => DecimalMath::currency($entry->original_credit_amount),
                'gl_entry_id' => $entry->gl_entry_id,
            ],
            'proposed' => [
                'debit_amount' => '0.00',
                'credit_amount' => DecimalMath::currency($postedInvoice->grand_total),
                'amount' => DecimalMath::currency($postedInvoice->grand_total),
                'remaining_amount' => DecimalMath::currency($postedInvoice->grand_total),
                'open' => true,
                'fully_applied' => false,
            ],
            'known_bad_state' => DecimalMath::compare($entry->debit_amount, $postedInvoice->grand_total) === 0
                && DecimalMath::compare($entry->credit_amount, '0') === 0
                && DecimalMath::compare($entry->remaining_amount, $postedInvoice->grand_total) === 0
                && (bool) $entry->open
                && ! (bool) $entry->fully_applied,
            'already_repaired' => DecimalMath::compare($entry->debit_amount, '0') === 0
                && DecimalMath::compare($entry->credit_amount, $postedInvoice->grand_total) === 0
                && DecimalMath::compare($entry->amount, $postedInvoice->grand_total) === 0
                && DecimalMath::compare($entry->remaining_amount, $postedInvoice->grand_total) === 0
                && (bool) $entry->open
                && ! (bool) $entry->fully_applied,
        ];
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private function createNeutralizerGlEntry(GlEntry $original, array $inspection, ?int $actorId): GlEntry
    {
        return $this->generalLedgerService->appendLegacyNeutralizer(
            $original,
            $actorId,
            $inspection['incident_identifier'],
            'Neutralize malformed one-sided historical purchase invoice G/L row.',
            $inspection['currency_code'],
        );
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private function postReplacementLiabilityTransaction(array $inspection, ?int $actorId): PostingTransaction
    {
        return $this->generalLedgerService->postTransaction([
            [
                'account_id' => $inspection['gl']['grni_account_id'],
                'debit_amount' => $inspection['amount'],
                'credit_amount' => '0',
                'description' => 'Replacement purchase clearing liability for historical incident '.$inspection['invoice_number'],
                'source_type' => SourceType::ITEM->value,
                'source_number' => $inspection['invoice_number'],
                'posting_group_source' => 'purchase_invoice_incident_repair',
            ],
            [
                'account_id' => $inspection['payables_account_id'],
                'debit_amount' => '0',
                'credit_amount' => $inspection['amount'],
                'description' => 'Replacement vendor payable for historical incident '.$inspection['invoice_number'],
                'source_type' => SourceType::VENDOR->value,
                'source_number' => $inspection['invoice_number'],
                'posting_group_source' => 'purchase_invoice_incident_repair',
                'vendor_ledger_entry_id' => $inspection['vendor_ledger']['id'],
            ],
        ], [
            'business_id' => $inspection['business_id'],
            'posting_date' => $inspection['posting_date'],
            'document_date' => $inspection['document_date'] ?? $inspection['posting_date'],
            'source_module' => 'purchases',
            'source_type' => SourceType::VENDOR->value,
            'source_id' => $inspection['posted_purchase_invoice_id'],
            'source_number' => $inspection['invoice_number'],
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $inspection['invoice_number'],
            'description' => 'Balanced replacement liability transaction for historical purchase invoice incident '.$inspection['invoice_number'],
            'currency_code' => $inspection['currency_code'],
            'exchange_rate' => $inspection['currency_factor'],
            'dimensions' => [
                'repair_version' => self::REPAIR_VERSION,
                'incident_identifier' => $inspection['incident_identifier'],
                'purchase_order_number' => $inspection['purchase_order_number'],
            ],
            'actor_id' => $actorId,
            'transaction_key' => $this->replacementTransactionKey($inspection['invoice_number']),
            'idempotency_key' => $this->replacementTransactionKey($inspection['invoice_number']),
            'reason' => 'Replace malformed one-sided historical purchase invoice liability G/L groups.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private function repairVendorLedgerEntry(array $inspection, int $replacementPayablesGlEntryId, ?int $actorId): void
    {
        $this->vendorLedgerNormalizationService->normalizeMalformedPurchaseInvoice(
            (int) $inspection['vendor_ledger']['id'],
            $inspection['vendor_ledger']['current'],
            $replacementPayablesGlEntryId,
            $inspection['incident_identifier'],
            'Normalize malformed historical purchase invoice vendor ledger representation.',
            $actorId,
        );
    }

    /**
     * @param  array<string, mixed>  $inspection
     */
    private function repairItemLedgerEntries(array $inspection): void
    {
        foreach ($inspection['item_ledger_entries'] as $plan) {
            ItemLedgerEntry::query()
                ->whereKey($plan['item_ledger_entry_id'])
                ->update([
                    'cost_amount_actual' => $plan['authoritative_value_entry_cost'],
                    'purchase_amount_actual' => $plan['authoritative_value_entry_cost'],
                    'cost_amount_expected' => $plan['canonical_cost_amount_expected'],
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, mixed>  $glPlan
     * @param  array<string, mixed>  $vendorPlan
     * @param  array<int, array<string, mixed>>  $ilePlans
     */
    private function assertRepairableState(array $glPlan, array $vendorPlan, array $ilePlans, ?PostingTransaction $replacementTransaction): void
    {
        if ($replacementTransaction) {
            throw new BusinessException('A replacement repair transaction already exists but the repair is not fully complete; aborting.');
        }

        if ($glPlan['neutralizer_entries']->isNotEmpty()) {
            throw new BusinessException('Repair neutralizer G/L entries already exist but the repair is not fully complete; aborting.');
        }

        if ($glPlan['malformed_group_count'] !== 3 || $glPlan['malformed_entry_count'] !== 3) {
            throw new BusinessException('The malformed historical G/L entry shape does not match the known incident.');
        }

        if ($glPlan['grni_account_count'] !== 1
            || $glPlan['credit_payables_count'] !== 1
            || DecimalMath::compare($glPlan['debit_total'], self::EXPECTED_INVOICE_TOTAL) !== 0
            || DecimalMath::compare($glPlan['credit_total'], self::EXPECTED_INVOICE_TOTAL) !== 0) {
            throw new BusinessException('The malformed historical G/L accounts or amounts do not match the known incident.');
        }

        if ((string) ($glPlan['grni_account_number'] ?? '') !== self::EXPECTED_GRNI_ACCOUNT_NUMBER) {
            throw new BusinessException('The malformed historical G/L debit rows do not use the known incident GRNI account.');
        }

        if (! $vendorPlan['known_bad_state']) {
            throw new BusinessException('The vendor ledger entry no longer matches the known historical incorrect state.');
        }

        foreach ($ilePlans as $plan) {
            if ($plan['already_synced']) {
                throw new BusinessException('A receipt item ledger entry is already synchronized while the rest of the repair is incomplete; aborting.');
            }

            if (! $plan['expected_cost_precondition_met']) {
                throw new BusinessException('A receipt item ledger entry expected-cost state does not match the known incident; aborting.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $glPlan
     * @param  array<string, mixed>  $vendorPlan
     * @param  array<int, array<string, mixed>>  $ilePlans
     */
    private function isAlreadyRepaired(?PostingTransaction $replacementTransaction, array $glPlan, array $vendorPlan, array $ilePlans): bool
    {
        return $replacementTransaction !== null
            && $glPlan['neutralizer_entries']->count() === 3
            && $vendorPlan['already_repaired']
            && collect($ilePlans)->every(fn (array $plan): bool => (bool) $plan['already_synced']);
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<string, mixed>
     */
    private function repairableBeforeState(array $inspection): array
    {
        return [
            'gl' => $inspection['gl']['malformed_summary'],
            'vendor_ledger' => $inspection['vendor_ledger']['current'],
            'item_ledger_entries' => $inspection['item_ledger_entries'],
        ];
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<string, mixed>
     */
    private function repairableAfterState(array $inspection): array
    {
        return [
            'replacement_transaction_id' => $inspection['replacement_transaction_id'],
            'neutralizer_gl_entry_ids' => $inspection['gl']['neutralizer_entry_ids'],
            'vendor_ledger' => $inspection['vendor_ledger']['current'],
            'item_ledger_entries' => $inspection['item_ledger_entries'],
        ];
    }

    private function replacementTransactionKey(string $invoiceNumber): string
    {
        return 'PURCHASE_INVOICE_INCIDENT_REPAIR:'.$invoiceNumber.':REPLACEMENT';
    }

    private function incidentIdentifier(string $invoiceNumber, string $orderNumber, int $businessId): string
    {
        return "{$invoiceNumber}|{$orderNumber}|business:{$businessId}|".self::REPAIR_VERSION;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function one($query, string $label)
    {
        $records = $query->limit(2)->get();

        if ($records->count() !== 1) {
            throw new BusinessException("{$label} is not uniquely identifiable; found {$records->count()} matching records, so no incident repair is safe.");
        }

        return $records->first();
    }
}
