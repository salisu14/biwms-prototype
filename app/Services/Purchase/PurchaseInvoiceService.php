<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Accounting\PostingIntent;
use App\Enums\ApprovalStatus;
use App\Enums\ItemLedgerEntryType;
use App\Enums\PostingIntentLineType;
use App\Enums\PostingIntentMode;
use App\Enums\PostingLcyOnlyReason;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Exceptions\NumberSeriesException;
use App\Exceptions\PostingSetupException;
use App\Models\GeneralPostingSetup;
use App\Models\GlEntry;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\Accounting\ControlAccountAssignmentService;
use App\Services\Accounting\GeneralLedgerPostingKernel;
use App\Services\Business\BusinessContextService;
use App\Services\Business\BusinessOwnershipService;
use App\Services\Finance\GeneralLedgerService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Services\Inventory\ValueEntryService;
use App\Services\NumberSeriesService;
use App\Services\VatService;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use App\Support\DecimalRounding;
use App\Support\PurchasingCurrency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PurchaseInvoiceService
{
    public function __construct(private readonly NumberSeriesService $numberSeriesService) {}

    public function createFromOrder(PurchaseOrder $order): PurchaseInvoice
    {
        return DB::transaction(function () use ($order): PurchaseInvoice {
            $order = PurchaseOrder::query()
                ->with(['vendor', 'lines.item'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->lines->isEmpty()) {
                throw new \RuntimeException("Purchase Order {$order->order_number} must have at least one line before it can be invoiced.");
            }

            $linesToInvoice = $order->lines
                ->map(function ($line): array {
                    $quantityToInvoice = max(0, (float) $line->received_quantity - (float) $line->invoiced_quantity);

                    return [
                        'line' => $line,
                        'quantity' => $quantityToInvoice,
                    ];
                })
                ->filter(fn (array $row): bool => (float) $row['quantity'] > 0)
                ->values();

            // Legacy-safe fallback: if status says received but line received quantities were never updated,
            // invoice remaining ordered qty minus already invoiced qty.
            if ($linesToInvoice->isEmpty() && $order->status === PurchaseOrderStatus::RECEIVED) {
                $linesToInvoice = $order->lines
                    ->map(function ($line): array {
                        $quantityToInvoice = max(0, (float) $line->quantity - (float) $line->invoiced_quantity);

                        return [
                            'line' => $line,
                            'quantity' => $quantityToInvoice,
                        ];
                    })
                    ->filter(fn (array $row): bool => (float) $row['quantity'] > 0)
                    ->values();
            }

            if ($linesToInvoice->isEmpty()) {
                throw new \RuntimeException('Nothing to invoice. All received quantities are already invoiced.');
            }

            // The authorized PO/document rate is authoritative for the invoice.
            // Foreign-currency orders without a valid rate fail closed.
            $currencyCode = $order->currency_code ?: PurchasingCurrency::LCY_CODE;
            $currencyFactor = $order->resolvedCurrencyFactor();

            $invoice = PurchaseInvoice::create([
                'business_id' => $order->business_id ?? app(BusinessContextService::class)->resolveId(),
                'document_number' => $this->generateNumber(),
                'external_document_number' => null,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'vendor_id' => $order->vendor_id,
                'vendor_name' => $order->vendor_name ?? $order->vendor?->vendor_name,
                'vendor_address' => $order->vendor?->address,
                'general_business_posting_group_id' => $order->general_business_posting_group_id,
                'vendor_posting_group_id' => $order->vendor_posting_group_id,
                'vat_business_posting_group_id' => $order->vat_business_posting_group_id,
                'location_id' => $order->location_id,
                'posting_date' => now()->toDateString(),
                'document_date' => now()->toDateString(),
                'due_date' => now()->addDays((int) ($order->payment_terms ?: 30))->toDateString(),
                'currency_code' => $currencyCode,
                'currency_factor' => $currencyFactor,
                'amount_paid' => 0,
                'remaining_amount' => 0,
                'paid_in_full' => false,
                'status' => ApprovalStatus::APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'cancelled' => false,
            ]);

            $totalAmount = 0.0;
            $totalVat = 0.0;
            $lineNo = 0;

            foreach ($linesToInvoice as $row) {
                $lineNo += 10;
                $line = $row['line'];
                $quantity = (float) $row['quantity'];
                $conversionFactor = (float) ($line->qty_per_unit_of_measure ?? 0);
                if ($conversionFactor <= 0) {
                    $conversionFactor = (float) ($line->item?->getConversionFactorForUom($line->unit_of_measure ?: $line->item?->base_unit_of_measure) ?? 1);
                }
                $conversionFactor = $conversionFactor > 0 ? $conversionFactor : 1.0;
                $quantityBase = $quantity * $conversionFactor;
                $unitCost = (float) $line->unit_cost;
                $lineTotal = $quantity * $unitCost;
                $vatAmount = $lineTotal * ((float) $line->vat_percentage / 100);
                $amountIncludingVat = $lineTotal + $vatAmount;

                $invoice->lines()->create([
                    'line_number' => $lineNo,
                    'po_line_id' => $line->id,
                    'po_line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->description,
                    'variant_code' => $line->variant_code,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->item?->inventory_posting_group_id,
                    'quantity' => $quantity,
                    'unit_of_measure_code' => $line->unit_of_measure,
                    'qty_per_unit_of_measure' => $conversionFactor,
                    'quantity_base' => $quantityBase,
                    'unit_cost' => $unitCost,
                    'unit_cost_lcy' => PurchasingCurrency::accountingLcy($unitCost, $currencyFactor),
                    'line_total' => $lineTotal,
                    'line_total_lcy' => PurchasingCurrency::accountingLcy($lineTotal, $currencyFactor),
                    'line_discount_amount' => 0,
                    'line_discount_percent' => 0,
                    'vat_code' => $line->vat_code,
                    'vat_percentage' => $line->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'vat_amount_lcy' => PurchasingCurrency::accountingLcy($vatAmount, $currencyFactor),
                    'amount_including_vat' => $amountIncludingVat,
                    'amount_including_vat_lcy' => PurchasingCurrency::accountingLcy($amountIncludingVat, $currencyFactor),
                    'posting_date' => $invoice->posting_date,
                ]);

                $line->increment('invoiced_quantity', $quantity);
                $totalAmount += $lineTotal;
                $totalVat += $vatAmount;
            }

            $grandTotal = $totalAmount + $totalVat;

            $invoice->update([
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $grandTotal,
                'remaining_amount' => $grandTotal,
                'total_amount_lcy' => PurchasingCurrency::accountingLcy($totalAmount, $currencyFactor),
                'total_vat_lcy' => PurchasingCurrency::accountingLcy($totalVat, $currencyFactor),
                'grand_total_lcy' => PurchasingCurrency::accountingLcy($grandTotal, $currencyFactor),
                'remaining_amount_lcy' => PurchasingCurrency::accountingLcy($grandTotal, $currencyFactor),
            ]);

            $order->refresh();

            return $invoice->fresh('lines');
        });
    }

    public function post(PurchaseInvoice $invoice): PostedPurchaseInvoice
    {
        return DB::transaction(function () use ($invoice): PostedPurchaseInvoice {
            $invoice = PurchaseInvoice::query()
                ->with(['lines.item', 'vendor', 'purchaseOrder'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->isPosted()) {
                throw new \RuntimeException('Purchase invoice is already posted.');
            }

            if ($invoice->status !== ApprovalStatus::APPROVED) {
                throw new \RuntimeException('Only approved purchase invoices can be posted.');
            }

            $invoice->loadMissing(['lines.item', 'vendor', 'purchaseOrder']);

            if ($invoice->lines->isEmpty()) {
                throw new \RuntimeException('No lines to post for this purchase invoice.');
            }

            // Ownership at posting comes ONLY from persisted authoritative state.
            // The active session must never be adopted at the posting boundary: a
            // null-owned document must not be silently reparented by whoever
            // happens to post it.
            $ownership = app(BusinessOwnershipService::class);
            $invoiceBusinessId = $invoice->business_id;

            if ($invoice->purchaseOrder !== null) {
                $orderBusinessId = $ownership->requirePersistedId(
                    $invoice->purchaseOrder->business_id,
                    'purchase order',
                );

                $invoiceBusinessId = $ownership->requirePersistedId($invoiceBusinessId, 'purchase invoice');

                if ($invoiceBusinessId !== $orderBusinessId) {
                    throw new BusinessException(
                        'The purchase invoice business does not match its purchase order business.',
                        title: 'Business ownership mismatch',
                        field: 'business_id',
                    );
                }
            } else {
                $invoiceBusinessId = $ownership->requirePersistedId($invoiceBusinessId, 'purchase invoice');
            }

            $businessId = $invoiceBusinessId;

            $this->assertPostingSetupComplete($invoice);

            // Resolve the authoritative document factor once and fail closed
            // before any accounting side effect when it is unresolved.
            $currencyFactor = $this->resolveInvoiceCurrencyFactor($invoice);

            // Deterministic, side-effect-free plan: allocate each line's
            // authoritative LCY value across its receipt chunks with a
            // final-chunk residual so the valuation legs sum to the commercial
            // clearing debit exactly.
            $linePlans = $this->planLineValuations($invoice, $currencyFactor);

            // Reject unsupported expected-cost G/L combinations before any
            // inventory value entry, posted snapshot or subledger write.
            $this->assertExpectedCostClearingSupported($invoice, $currencyFactor, $linePlans);

            // Build the FINAL liability intent once and preflight exactly that
            // intent, so no economic field, line reference or rounding line can
            // change between preflight and posting.
            $liabilityIntent = $this->buildLiabilityIntent($invoice, $currencyFactor, $businessId);

            app(GeneralLedgerPostingKernel::class)->preflight(PostingIntent::fromArray([
                ...$liabilityIntent['meta'],
                'lines' => $liabilityIntent['lines'],
            ]));

            // ---- Accounting side effects begin here. ----

            foreach ($invoice->lines as $line) {
                if (! $line->item) {
                    throw new \RuntimeException("Item is missing for purchase invoice line {$line->id}.");
                }

                $itemLedgerEntry = null;
                $plan = $linePlans[$line->id] ?? ['chunks' => [], 'direct_lcy' => null];

                if ($plan['chunks'] !== []) {
                    foreach ($plan['chunks'] as $chunk) {
                        $actualValueEntry = app(ValueEntryService::class)->actualizePurchaseReceiptForInvoiceLine(
                            receiptEntry: $chunk['entry'],
                            invoice: $invoice,
                            line: $line,
                            quantityBase: $chunk['quantity'],
                            costAmountActual: (float) $chunk['allocated_lcy'],
                        );

                        app(ValueEntryAccountingOrchestrator::class)->post($actualValueEntry);
                        $itemLedgerEntry ??= $chunk['entry'];
                    }

                    if ($itemLedgerEntry) {
                        $line->forceFill(['item_ledger_entry_id' => $itemLedgerEntry->id])->save();
                    }
                } else {
                    $itemLedgerEntry = $this->createItemLedgerEntryForLine($invoice, $line, $currencyFactor);
                }

                if ($itemLedgerEntry && $plan['chunks'] === []) {
                    $line->forceFill(['item_ledger_entry_id' => $itemLedgerEntry->id])->save();
                    app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($itemLedgerEntry);
                }

            }

            $posted = PostedPurchaseInvoice::query()->firstOrCreate(
                ['document_number' => $invoice->document_number],
                [
                    'business_id' => $businessId,
                    'external_document_number' => $invoice->external_document_number,
                    'order_id' => $invoice->order_id,
                    'order_number' => $invoice->order_number,
                    'vendor_id' => $invoice->vendor_id,
                    'vendor_name' => $invoice->vendor_name,
                    'vendor_address' => $invoice->vendor_address,
                    'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
                    'vendor_posting_group_id' => $invoice->vendor_posting_group_id,
                    'vat_business_posting_group_id' => $invoice->vat_business_posting_group_id,
                    'location_id' => $invoice->location_id,
                    'posting_date' => $invoice->posting_date,
                    'document_date' => $invoice->document_date,
                    'due_date' => $invoice->due_date,
                    'vat_date' => $invoice->vat_date,
                    'total_amount' => $invoice->total_amount,
                    'total_vat' => $invoice->total_vat,
                    'grand_total' => $invoice->grand_total,
                    'total_amount_lcy' => $invoice->total_amount_lcy,
                    'total_vat_lcy' => $invoice->total_vat_lcy,
                    'grand_total_lcy' => $invoice->grand_total_lcy,
                    'currency_code' => $invoice->currency_code,
                    'currency_factor' => $invoice->currency_factor,
                    'amount_paid' => $invoice->amount_paid ?? 0,
                    'remaining_amount' => $invoice->remaining_amount,
                    'remaining_amount_lcy' => $invoice->remaining_amount_lcy,
                    'paid_in_full' => $invoice->paid_in_full ?? false,
                    'paid_in_full_date' => $invoice->paid_in_full_date,
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                    'cancelled' => false,
                    'dimensions' => $invoice->dimensions,
                ]
            );

            $posted->lines()->delete();

            foreach ($invoice->lines as $line) {
                PostedPurchaseInvoiceLine::query()->create([
                    'posted_purchase_invoice_id' => $posted->id,
                    'po_line_id' => $line->po_line_id,
                    'po_line_number' => $line->po_line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->item_description,
                    'variant_code' => $line->variant_code,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->inventory_posting_group_id,
                    'gl_account_id' => $line->gl_account_id,
                    'gl_account_number' => $line->gl_account_number,
                    'gl_account_name' => $line->gl_account_name,
                    'quantity' => $line->quantity,
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $line->qty_per_unit_of_measure,
                    'quantity_base' => $line->quantity_base,
                    'unit_cost' => $line->unit_cost,
                    'unit_cost_lcy' => $line->unit_cost_lcy,
                    'line_total' => $line->line_total,
                    'line_total_lcy' => $line->line_total_lcy,
                    'line_discount_amount' => $line->line_discount_amount,
                    'line_discount_percent' => $line->line_discount_percent,
                    'vat_code' => $line->vat_code,
                    'vat_percentage' => $line->vat_percentage,
                    'vat_amount' => $line->vat_amount,
                    'vat_amount_lcy' => $line->vat_amount_lcy,
                    'amount_including_vat' => $line->amount_including_vat,
                    'amount_including_vat_lcy' => $line->amount_including_vat_lcy,
                    'lot_number' => $line->lot_number,
                    'serial_number' => $line->serial_number,
                    'expiration_date' => $line->expiration_date,
                    'dimensions' => $line->dimensions,
                    'item_ledger_entry_id' => $line->item_ledger_entry_id,
                    'gl_entry_id' => $line->gl_entry_id,
                    'line_number' => $line->line_number,
                    'posting_date' => $invoice->posting_date,
                ]);
            }

            $postingTransaction = app(GeneralLedgerService::class)->postTransaction(
                $liabilityIntent['lines'],
                $liabilityIntent['meta'],
            );

            $invoice->update([
                'status' => ApprovalStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            $invoice->refresh();

            $ledgerEntryExists = VendorLedgerEntry::query()
                ->where('document_type', 'PURCHASE_INVOICE')
                ->where('document_number', $invoice->document_number)
                ->where('vendor_id', $invoice->vendor_id)
                ->exists();

            if (! $ledgerEntryExists) {
                // The vendor ledger's initial carrying amount must equal the
                // A/P control G/L recognition exactly; both derive from this
                // single authoritative accounting LCY value.
                $vendorLedgerEntry = VendorLedgerEntry::createFromInvoice(
                    $posted,
                    PurchasingCurrency::accountingLcy(DecimalMath::amount($posted->grand_total), $currencyFactor),
                );

                GlEntry::query()
                    ->where('posting_transaction_id', $postingTransaction->id)
                    ->where('chart_of_account_id', $invoice->vendor?->vendorPostingGroup?->payables_account_id)
                    ->where('document_type', 'PURCHASE_INVOICE')
                    ->where('document_number', $invoice->document_number)
                    ->update(['vendor_ledger_entry_id' => $vendorLedgerEntry->id]);
            }

            if ($invoice->purchaseOrder) {
                $invoice->purchaseOrder->refreshLifecycleStatus();
            }

            return $posted;
        });
    }

    /**
     * Build the authoritative currency-aware purchase invoice liability intent.
     *
     * This is constructed once from pre-existing invoice economics only, so the
     * exact same value object is used for the preflight and for the posting:
     * no line reference (e.g. a generated item-ledger id), rounding line or
     * ownership field can change between validating and posting.
     *
     * @return array{meta: array<string, mixed>, lines: array<int, array<string, mixed>>}
     */
    private function buildLiabilityIntent(PurchaseInvoice $invoice, string $currencyFactor, ?int $businessId): array
    {
        return [
            'meta' => [
                'business_id' => $businessId,
                'posting_date' => $invoice->posting_date,
                'document_date' => $invoice->document_date ?? $invoice->posting_date,
                'source_module' => 'purchases',
                'source_type' => SourceType::VENDOR->value,
                'source_id' => $invoice->id,
                'source_number' => $invoice->document_number,
                'document_type' => 'PURCHASE_INVOICE',
                'document_number' => $invoice->document_number,
                'external_document_number' => $invoice->external_document_number,
                'description' => "Purchase Invoice {$invoice->document_number}",
                'currency_code' => $invoice->currency_code ?: 'NGN',
                'exchange_rate' => $currencyFactor,
                'mode' => PostingIntentMode::CURRENCY_AWARE->value,
                'dimensions' => $invoice->dimensions ?? [],
                'actor_id' => Auth::id(),
                'transaction_key' => "PURCHASE_INVOICE:{$invoice->document_number}:LIABILITY",
                'idempotency_key' => hash('sha256', "purchase-invoice-liability|{$invoice->id}|{$invoice->document_number}"),
            ],
            'lines' => $this->liabilityTransactionLines($invoice, $currencyFactor),
        ];
    }

    /**
     * Build the currency-aware purchase invoice liability intent.
     *
     * Commercial lines are document-currency amounts carried with an explicit
     * document trace; their LCY economics are the authoritative accounting LCY
     * values derived with the certified accounting rule. The A/P control credit
     * carries the document grand total and its LCY equivalent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function liabilityTransactionLines(PurchaseInvoice $invoice, string $currencyFactor): array
    {
        $invoice->loadMissing(['lines.item', 'vendor.vendorPostingGroup.payablesAccount']);
        $payablesAccount = $invoice->vendor?->getPayablesAccount();

        if (! $invoice->vendor || ! $payablesAccount) {
            throw new PostingSetupException("A/P account is missing for purchase invoice {$invoice->document_number}.");
        }

        $lines = [];
        $debitTotalFcy = '0';

        foreach ($invoice->lines as $line) {
            if (! $line->item) {
                throw new PostingSetupException("Item is missing for purchase invoice line {$line->id}.");
            }

            $setup = $this->generalPostingSetupFor($invoice->vendor, $line->item);

            if (! $setup) {
                $vendorRef = $invoice->vendor->vendor_code ?: $invoice->vendor->vendor_name ?: (string) $invoice->vendor->id;

                throw new PostingSetupException("Posting setup missing for vendor {$vendorRef} and item {$line->item->item_code}");
            }

            $lineAmountFcy = DecimalMath::amount($line->line_total);

            if (DecimalMath::isPositive($lineAmountFcy)) {
                $purchaseAccount = $line->item->isInventoryItem()
                    ? $setup->getPurchaseClearingAccount()
                    : $setup->getExpensePurchaseAccount();

                if (! $purchaseAccount) {
                    throw new PostingSetupException($line->item->isInventoryItem()
                        ? $this->purchaseClearingMissingMessage($setup)
                        : "Purchase account missing in posting setup for item {$line->item->item_code}");
                }

                $lines[] = [
                    'account_id' => $purchaseAccount->id,
                    'debit_amount' => PurchasingCurrency::accountingLcy($lineAmountFcy, $currencyFactor),
                    'credit_amount' => '0',
                    'line_type' => PostingIntentLineType::DOCUMENT_MONETARY->value,
                    'document_debit_amount' => $lineAmountFcy,
                    'document_credit_amount' => '0',
                    'description' => ($line->item->isInventoryItem() ? 'Purchase clearing: ' : 'Purchase expense: ')
                        .($line->item_description ?? $line->item->description ?? 'Purchase Invoice Line'),
                    'source_type' => SourceType::ITEM->value,
                    'source_number' => $line->item->item_code,
                    'posting_group_source' => 'general_posting_setup',
                    'dimensions' => $line->dimensions ?? [],
                ];

                $debitTotalFcy = DecimalMath::add($debitTotalFcy, $lineAmountFcy, DecimalPrecision::AMOUNT_SCALE);
            }

            $vatAmountFcy = DecimalMath::amount($line->vat_amount);

            if (DecimalMath::isPositive($vatAmountFcy)) {
                $vatSetup = app(VatService::class)->resolveSetup(
                    $invoice->vendor->vat_business_posting_group_id ?? $invoice->vendor->vat_bus_posting_group,
                    $line->item->vat_product_posting_group_id
                );

                if ($vatSetup?->purchase_vat_account_id) {
                    $lines[] = [
                        'account_id' => $vatSetup->purchase_vat_account_id,
                        'debit_amount' => PurchasingCurrency::accountingLcy($vatAmountFcy, $currencyFactor),
                        'credit_amount' => '0',
                        'line_type' => PostingIntentLineType::DOCUMENT_MONETARY->value,
                        'document_debit_amount' => $vatAmountFcy,
                        'document_credit_amount' => '0',
                        'description' => 'VAT Input: '.($line->item_description ?? $line->item->description ?? 'Purchase Invoice Line'),
                        'source_type' => SourceType::ITEM->value,
                        'source_number' => $line->item->item_code,
                        'posting_group_source' => 'vat_posting_setup',
                        'dimensions' => $line->dimensions ?? [],
                    ];

                    $debitTotalFcy = DecimalMath::add($debitTotalFcy, $vatAmountFcy, DecimalPrecision::AMOUNT_SCALE);
                }
            }
        }

        $grandTotalFcy = DecimalMath::amount($invoice->grand_total);

        if (abs((float) $grandTotalFcy - (float) $debitTotalFcy) > 0.0001) {
            throw new PostingSetupException("Purchase invoice {$invoice->document_number} posting lines do not match the invoice total.");
        }

        $payableLcy = PurchasingCurrency::accountingLcy($grandTotalFcy, $currencyFactor);

        $debitTotalLcy = '0';

        foreach ($lines as $line) {
            $debitTotalLcy = DecimalMath::add($debitTotalLcy, $line['debit_amount'], DecimalPrecision::CURRENCY_SCALE);
        }

        // The per-line LCY rounding and the grand-total LCY rounding can differ
        // by a minor unit. Post the residual explicitly; never absorb it
        // silently and never adjust the A/P control amount.
        $roundingResidual = DecimalMath::sub($payableLcy, $debitTotalLcy, DecimalPrecision::CURRENCY_SCALE);

        // Bound the residual to the legitimate line-vs-header rounding of the
        // independently converted monetary components. Anything larger is a
        // substantive mismatch wearing a rounding label, so it must fail closed
        // rather than move a material amount through the rounding account.
        $roundingBound = DecimalMath::mul('0.01', (string) max(1, count($lines)), DecimalPrecision::CURRENCY_SCALE);

        if (DecimalMath::compare(DecimalMath::abs($roundingResidual, DecimalPrecision::CURRENCY_SCALE), $roundingBound) > 0) {
            throw new PostingSetupException(
                "Purchase invoice {$invoice->document_number} has an LCY rounding difference of {$roundingResidual} "
                ."that exceeds the supported per-component rounding bound of {$roundingBound}."
            );
        }

        if (! DecimalMath::isZero($roundingResidual)) {
            $roundingAccount = $invoice->vendor->vendorPostingGroup?->invoiceRoundingAccount;

            if (! $roundingAccount) {
                throw new PostingSetupException(
                    "Purchase invoice {$invoice->document_number} has an LCY rounding difference of {$roundingResidual} "
                    .'that cannot be posted: configure an Invoice Rounding Account on the vendor posting group.'
                );
            }

            $isDebitResidual = DecimalMath::isPositive($roundingResidual);

            $lines[] = [
                'account_id' => $roundingAccount->id,
                'debit_amount' => $isDebitResidual ? $roundingResidual : '0',
                'credit_amount' => $isDebitResidual ? '0' : DecimalMath::abs($roundingResidual, DecimalPrecision::CURRENCY_SCALE),
                'line_type' => PostingIntentLineType::LCY_ONLY->value,
                'lcy_only_reason' => PostingLcyOnlyReason::ROUNDING->value,
                'description' => 'Purchase invoice LCY rounding difference',
                'source_type' => SourceType::VENDOR->value,
                'source_number' => $invoice->vendor->vendor_code,
                'dimensions' => $invoice->dimensions ?? [],
            ];
        }

        $lines[] = [
            'account_id' => $payablesAccount->id,
            'debit_amount' => '0',
            'credit_amount' => $payableLcy,
            'line_type' => PostingIntentLineType::DOCUMENT_MONETARY->value,
            'document_debit_amount' => '0',
            'document_credit_amount' => $grandTotalFcy,
            'description' => "Payable to {$invoice->vendor->vendor_name}",
            'source_type' => SourceType::VENDOR->value,
            'source_number' => $invoice->vendor->vendor_code,
            'posting_group_source' => 'vendor_posting_group',
            'dimensions' => $invoice->dimensions ?? [],
        ];

        return $lines;
    }

    /**
     * Resolve the authoritative document factor once for the invoice.
     *
     * NGN resolves to 1; a foreign invoice requires an explicit, finite,
     * positive factor and otherwise fails closed before any side effect.
     */
    private function resolveInvoiceCurrencyFactor(PurchaseInvoice $invoice): string
    {
        try {
            return PurchasingCurrency::factorFor($invoice->currency_code, $invoice->currency_factor);
        } catch (InvalidArgumentException $exception) {
            throw new BusinessException(
                "Purchase invoice {$invoice->document_number} cannot be posted: ".$exception->getMessage()
            );
        }
    }

    /**
     * The authoritative LCY value of a purchase invoice line at the accounting
     * rule used by the posting boundary and the inventory valuation source.
     */
    private function lineValueLcy(PurchaseInvoiceLine $line, string $currencyFactor): string
    {
        return PurchasingCurrency::accountingLcy(DecimalMath::amount($line->line_total), $currencyFactor);
    }

    /**
     * Deterministic, side-effect-free valuation plan for every invoice line.
     *
     * For a receipt-backed line the authoritative line LCY value is allocated
     * across the receipt chunks that the invoice will actualize, using
     * cumulative rounding with an exact final-chunk residual. Each chunk's
     * allocation is the amount posted as its inventory valuation, so the sum of
     * the valuation clearing legs equals the commercial clearing debit exactly.
     *
     * @return array<int, array{chunks: array<int, array{entry: ItemLedgerEntry, quantity: float, allocated_lcy: string}>, direct_lcy: ?string}>
     */
    private function planLineValuations(PurchaseInvoice $invoice, string $currencyFactor): array
    {
        $plans = [];

        foreach ($invoice->lines as $line) {
            $receiptEntries = $this->receiptItemLedgerEntriesForLine($line);

            if ($receiptEntries->isEmpty()) {
                $plans[$line->id] = [
                    'chunks' => [],
                    'direct_lcy' => $this->lineValueLcy($line, $currencyFactor),
                ];

                continue;
            }

            $quantityBase = $this->quantityBase($line, $line->item);
            $remaining = $quantityBase;
            $chunks = [];

            foreach ($receiptEntries as $receiptEntry) {
                if ($remaining <= 0.0001) {
                    break;
                }

                $available = $this->remainingExpectedQuantityBase($receiptEntry);

                if ($available <= 0) {
                    continue;
                }

                $take = min($remaining, $available);
                $chunks[] = ['entry' => $receiptEntry, 'quantity' => $take];
                $remaining -= $take;
            }

            if ($remaining > 0.0001) {
                throw new \RuntimeException('Purchase invoice quantity exceeds remaining received quantity available for actualization.');
            }

            $allocations = $this->allocateLineLcyAcrossChunks(
                $this->lineValueLcy($line, $currencyFactor),
                array_column($chunks, 'quantity'),
            );

            $plans[$line->id] = [
                'chunks' => array_map(
                    fn (array $chunk, string $allocated): array => [...$chunk, 'allocated_lcy' => $allocated],
                    $chunks,
                    $allocations,
                ),
                'direct_lcy' => null,
            ];
        }

        return $plans;
    }

    /**
     * Allocate a 2 dp LCY line total across receipt chunks by quantity using
     * cumulative rounding, so the allocated total equals the authoritative line
     * LCY exactly and no allocation is negative. The final chunk is pinned to
     * the exact residual so accumulated rounding can never leave a stranded
     * minor unit in the purchase clearing account.
     *
     * @param  array<int, float>  $quantities
     * @return array<int, string>
     */
    private function allocateLineLcyAcrossChunks(string $totalLcy, array $quantities): array
    {
        $count = count($quantities);

        if ($count === 0) {
            return [];
        }

        $totalQuantity = array_sum($quantities);
        $total = DecimalMath::of($totalLcy);
        $allocations = [];
        $previousCumulativeLcy = '0';
        $cumulativeQuantity = 0.0;

        foreach ($quantities as $index => $quantity) {
            if ($index === $count - 1 || $totalQuantity <= 0.0) {
                $allocations[] = DecimalMath::sub($totalLcy, $previousCumulativeLcy, DecimalPrecision::CURRENCY_SCALE);

                continue;
            }

            $cumulativeQuantity += $quantity;
            $cumulativeLcy = (string) $total
                ->multipliedBy(DecimalMath::of((string) $cumulativeQuantity))
                ->dividedBy(DecimalMath::of((string) $totalQuantity), DecimalPrecision::CURRENCY_SCALE, DecimalRounding::AMOUNT);

            $allocations[] = DecimalMath::sub($cumulativeLcy, $previousCumulativeLcy, DecimalPrecision::CURRENCY_SCALE);
            $previousCumulativeLcy = $cumulativeLcy;
        }

        return $allocations;
    }

    /**
     * Fail closed before any side effect when expected-cost inventory G/L
     * posting is enabled for a receipt-backed foreign-currency purchase
     * invoice. The receipt expected cost is recognised per chunk, so independent
     * per-chunk rounding cannot be guaranteed to clear the purchase clearing
     * account exactly, and no exchange-rate or purchase-price variance mechanism
     * is supported. This combination is fenced until an exact-allocation phase;
     * direct (non-receipt-backed) invoices and local-currency documents are
     * unaffected.
     *
     * @param  array<int, array{chunks: array<int, mixed>, direct_lcy: ?string}>  $linePlans
     */
    private function assertExpectedCostClearingSupported(
        PurchaseInvoice $invoice,
        string $currencyFactor,
        array $linePlans,
    ): void {
        if (! config('accounts.post_expected_inventory_cost_to_gl', false)) {
            return;
        }

        if (PurchasingCurrency::isLcyFactor($currencyFactor)) {
            return;
        }

        $receiptBacked = collect($linePlans)->contains(fn (array $plan): bool => $plan['chunks'] !== []);

        if (! $receiptBacked) {
            return;
        }

        throw new BusinessException(
            "Foreign-currency purchase invoice {$invoice->document_number} is backed by goods receipts and expected "
            .'inventory cost G/L posting is enabled. Per-chunk receipt rounding cannot be guaranteed to clear the '
            .'purchase clearing account exactly and no exchange-rate or purchase-price variance mechanism is '
            .'supported; disable expected inventory cost G/L posting or post this document in the local currency.'
        );
    }

    private function createItemLedgerEntryForLine(PurchaseInvoice $invoice, PurchaseInvoiceLine $line, string $currencyFactor): ?ItemLedgerEntry
    {
        $item = $line->item;

        if (! $item || ! $item->isInventoryItem()) {
            return null;
        }

        $quantityBase = $this->quantityBase($line, $item);

        if ($quantityBase <= 0) {
            throw new \RuntimeException("Quantity must be greater than zero for item {$item->item_code}");
        }

        // The valuation source entering inventory accounting is LCY; the
        // commercial document amount (line_total, unit_cost) stays FCY.
        $lineValueLcy = (float) $this->lineValueLcy($line, $currencyFactor);
        $locationId = $invoice->location_id ?? $item->location_id;

        if (! $locationId) {
            throw new \RuntimeException("Location is missing for item {$item->item_code} on purchase invoice {$invoice->document_number}.");
        }

        $entry = ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::PURCHASE,
            'document_type' => 'PURCHASE_INVOICE',
            'document_line_number' => $line->line_number ?? $line->id,
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => $quantityBase,
            'remaining_quantity' => $quantityBase,
            'open' => true,
            'posting_date' => $invoice->posting_date,
            'entry_date' => now(),
            'document_number' => $invoice->document_number,
            'source_id' => $invoice->id,
            'source_type' => PurchaseInvoice::class,
            'cost_amount_actual' => $lineValueLcy,
            'cost_amount_expected' => 0,
            'purchase_amount_actual' => $lineValueLcy,
            'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
        ]);

        $this->assertValueEntryCreated($entry);

        $item->increment('inventory', $quantityBase);

        return $entry;
    }

    public function assertPostingSetupComplete(PurchaseInvoice $invoice): void
    {
        $invoice->loadMissing(['lines.item', 'vendor']);

        if (! $invoice->vendor) {
            throw new PostingSetupException("Vendor is missing for purchase invoice {$invoice->document_number}.");
        }

        $this->assertVendorLinePostingSetupComplete(
            vendor: $invoice->vendor,
            lines: $invoice->lines,
            lineContext: 'purchase invoice line',
        );
    }

    public function assertPurchaseOrderPostingSetupComplete(PurchaseOrder $order): void
    {
        $order->loadMissing(['vendor', 'lines.item']);

        if ($order->lines->isEmpty()) {
            return;
        }

        if (! $order->vendor) {
            throw new PostingSetupException("Vendor is missing for purchase order {$order->order_number}.");
        }

        $this->assertVendorLinePostingSetupComplete(
            vendor: $order->vendor,
            lines: $order->lines,
            lineContext: 'purchase order line',
        );
    }

    /**
     * @param  iterable<int, PurchaseInvoiceLine|PurchaseOrderLine>  $lines
     */
    private function assertVendorLinePostingSetupComplete(Vendor $vendor, iterable $lines, string $lineContext): void
    {
        $this->assertVendorPayablesAccountPostable($vendor);

        foreach ($lines as $line) {
            if (! $line->item) {
                throw new PostingSetupException("Item is missing for {$lineContext} {$line->id}.");
            }

            $setup = $this->generalPostingSetupFor($vendor, $line->item);

            if (! $setup) {
                $vendorRef = $vendor->vendor_code ?: $vendor->vendor_name ?: (string) $vendor->id;

                throw new PostingSetupException("Posting setup missing for vendor {$vendorRef} and item {$line->item->item_code}");
            }

            if ($line->item->isInventoryItem() && ! $setup->getPurchaseClearingAccount()) {
                throw new PostingSetupException($this->purchaseClearingMissingMessage($setup));
            }

            if (! $line->item->isInventoryItem() && ! $setup->getExpensePurchaseAccount()) {
                $vendorRef = $vendor->vendor_code ?: $vendor->vendor_name ?: (string) $vendor->id;

                throw new PostingSetupException("Purchase account missing in posting setup for vendor {$vendorRef} and item {$line->item->item_code}");
            }
        }
    }

    private function assertVendorPayablesAccountPostable(Vendor $vendor): void
    {
        $payablesAccount = $vendor->getPayablesAccount();

        if (! $payablesAccount) {
            $vendorCode = $vendor->vendor_code ?: 'N/A';
            $vendorName = $vendor->vendor_name ?: 'N/A';
            $postingGroupId = $vendor->vendor_posting_group_id ?: 'N/A';

            throw new PostingSetupException(
                "No A/P account is configured for vendor '{$vendorName}' ({$vendorCode}). Set a Payables Account on Vendor Posting Group ID {$postingGroupId}."
            );
        }

        try {
            app(ControlAccountAssignmentService::class)->validateVendorPayables((int) $payablesAccount->id);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: 'The selected Payables Account must be a Balance Sheet liability/payables account and cannot be an Income Statement revenue or expense account.';

            throw new PostingSetupException(
                (string) $message,
                [
                    'vendor_id' => $vendor->id,
                    'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
                    'payables_account_id' => $payablesAccount->id,
                    'payables_account_number' => $payablesAccount->account_number,
                ],
                $exception,
            );
        }
    }

    private function generalPostingSetupFor(Vendor $vendor, Item $item): ?GeneralPostingSetup
    {
        $setup = $vendor->getPostingSetupFor($item);

        if (! $setup && $vendor->general_business_posting_group_id && $item->general_product_posting_group_id) {
            $setup = GeneralPostingSetup::query()
                ->where('general_business_posting_group_id', $vendor->general_business_posting_group_id)
                ->where('general_product_posting_group_id', $item->general_product_posting_group_id)
                ->where('blocked', false)
                ->first();
        }

        return $setup;
    }

    private function purchaseClearingMissingMessage(GeneralPostingSetup $setup): string
    {
        $setup->loadMissing(['generalBusinessPostingGroup', 'generalProductPostingGroup']);

        $businessGroup = $setup->generalBusinessPostingGroup?->code ?? (string) $setup->general_business_posting_group_id;
        $productGroup = $setup->generalProductPostingGroup?->code ?? (string) $setup->general_product_posting_group_id;

        return "Purchase Clearing Account is not configured for General Business Posting Group {$businessGroup} and General Product Posting Group {$productGroup}.";
    }

    /**
     * @return Collection<int, ItemLedgerEntry>
     */
    private function receiptItemLedgerEntriesForLine(PurchaseInvoiceLine $line): Collection
    {
        if (! $line->po_line_id) {
            return collect();
        }

        $line->loadMissing('purchaseOrderLine.purchaseOrder');
        $orderLine = $line->purchaseOrderLine;
        $order = $orderLine?->purchaseOrder;

        if (! $orderLine || ! $order) {
            return collect();
        }

        return ItemLedgerEntry::query()
            ->where('entry_type', ItemLedgerEntryType::PURCHASE)
            ->where('document_type', 'PURCHASE_RECEIPT')
            ->where('document_number', $order->order_number)
            ->where('document_line_number', $orderLine->line_number)
            ->where('item_id', $line->item_id)
            ->orderBy('id')
            ->get();
    }

    private function remainingExpectedQuantityBase(ItemLedgerEntry $receiptEntry): float
    {
        $expectedValueEntry = ValueEntry::query()
            ->where('item_ledger_entry_no', $receiptEntry->entry_number)
            ->where('document_no', $receiptEntry->document_number)
            ->where('document_line_no', $receiptEntry->document_line_number)
            ->where('value_entry_state', 'expected')
            ->first();

        return abs((float) ($expectedValueEntry?->remaining_quantity ?? $receiptEntry->remaining_quantity ?? 0));
    }

    private function quantityBase(PurchaseInvoiceLine $line, Item $item): float
    {
        $quantityBase = (float) ($line->quantity_base ?? 0);

        if ($quantityBase > 0) {
            return $quantityBase;
        }

        $conversionFactor = (float) ($line->qty_per_unit_of_measure ?: 0);

        if ($conversionFactor <= 0) {
            $conversionFactor = $item->getConversionFactorForUom($line->unit_of_measure_code ?: $item->base_unit_of_measure);
        }

        return (float) $line->quantity * ($conversionFactor > 0 ? $conversionFactor : 1.0);
    }

    private function assertValueEntryCreated(ItemLedgerEntry $entry): void
    {
        $exists = ValueEntry::query()
            ->where('item_ledger_entry_no', $entry->entry_number)
            ->where('document_no', $entry->document_number)
            ->where('document_line_no', $entry->document_line_number)
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Value Entry was not created for item ledger entry {$entry->entry_number}.");
        }
    }

    private function generateNumber(): string
    {
        try {
            return $this->numberSeriesService->getNextNoFromSeries(
                ['P-INV', 'PURCHASE_INVOICE', 'PI'],
                null,
                'Purchase Invoice'
            );
        } catch (NumberSeriesException $exception) {
            if ($exception->codeIdentifier() === 'number_series_line_missing'
                || str_contains($exception->getMessage(), 'No open Number Series Line exists')) {
                throw new NumberSeriesException(
                    'Purchase Invoice number series has no active line for the posting date. Configure a Number Series Line before posting.',
                    $exception->seriesCodes,
                    codeIdentifier: 'purchase_invoice_number_series_line_missing',
                    previous: $exception,
                );
            }

            throw $exception;
        }
    }
}
