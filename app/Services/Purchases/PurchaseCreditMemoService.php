<?php

declare(strict_types=1);

namespace App\Services\Purchases;

use App\Data\Purchases\PurchaseCreditMemoData;
use App\Data\Purchases\PurchaseCreditMemoLineData;
use App\Enums\ApprovalStatus;
use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseCreditMemoLine;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseCreditMemo;
use App\Models\PurchaseCreditMemoLine;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\Approval\ApprovalTemplateService;
use App\Services\Inventory\ItemApplicationService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Services\PostingService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PurchaseCreditMemoService
{
    public function __construct(
        protected PostingService $postingService,
        protected ApprovalTemplateService $approvalTemplateService,
    ) {}

    /**
     * @throws \Throwable
     */
    public function create(PurchaseCreditMemoData $data): PurchaseCreditMemo
    {
        return DB::transaction(function () use ($data) {
            if (count($data->lines) === 0) {
                throw ValidationException::withMessages(['lines' => 'Select at least one line to credit.']);
            }

            $vendor = Vendor::findOrFail($data->vendor_id);
            $correctedInvoice = $this->resolvePurchaseInvoice($data->corrects_invoice_id, $data->external_document_number);
            $postedCorrectedInvoice = $this->resolvePostedPurchaseInvoice($correctedInvoice);

            if ($correctedInvoice && ! $postedCorrectedInvoice) {
                throw ValidationException::withMessages([
                    'corrects_invoice_id' => 'The selected purchase invoice has not been posted yet.',
                ]);
            }

            if ($postedCorrectedInvoice) {
                $this->validateCreditQuantitiesAgainstInvoice($correctedInvoice, $data);
                $this->ensureInvoiceLinkedMemoHasExactLineage($postedCorrectedInvoice, $data->lines->toArray());
            }

            $memo = PurchaseCreditMemo::create([
                'business_id' => $data->business_id ?? (request()?->integer('business_id') ?: session('active_business_id')),
                'document_number' => PurchaseCreditMemo::generateNumber(),
                'vendor_id' => $data->vendor_id,
                'vendor_name' => $vendor->vendor_name,
                'corrects_invoice_id' => $data->corrects_invoice_id,
                'corrects_invoice_number' => $correctedInvoice?->document_number,
                'external_document_number' => $data->external_document_number,
                'posting_date' => $data->posting_date ?? $correctedInvoice?->posting_date ?? now(),
                'document_date' => $data->document_date ?? $correctedInvoice?->document_date ?? now(),
                'location_id' => $data->location_id ?? $correctedInvoice?->location_id,
                'currency_code' => $data->currency_code ?? $correctedInvoice?->currency_code ?? 'NGN',
                'reason_code' => $data->reason_code,
                'description' => $data->description,
                'status' => ApprovalStatus::DRAFT,
            ]);

            foreach ($data->lines as $index => $line) {
                $item = Item::findOrFail($line->item_id);
                $memo->lines()->create([
                    'line_number' => ($index + 1) * 10000,
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $line->description ?? $item->description,
                    'corrected_invoice_line_id' => $this->resolveCorrectedInvoiceLineId($correctedInvoice, $line),
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'tax_percent' => $line->tax_percent,
                    'general_product_posting_group_id' => $item->general_product_posting_group_id,
                    'unit_of_measure_code' => $item->uom_code ?? 'EA',
                ]);
            }

            return $memo;
        });
    }

    /**
     * @throws \Throwable
     */
    public function update(PurchaseCreditMemo $memo, PurchaseCreditMemoData $data): PurchaseCreditMemo
    {
        if ($memo->status !== ApprovalStatus::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Only draft credit memos can be modified.']);
        }

        return DB::transaction(function () use ($memo, $data) {
            if (count($data->lines) === 0) {
                throw ValidationException::withMessages(['lines' => 'Select at least one line to credit.']);
            }

            $vendor = Vendor::findOrFail($data->vendor_id);
            $correctedInvoice = $this->resolvePurchaseInvoice($data->corrects_invoice_id, $data->external_document_number);
            $postedCorrectedInvoice = $this->resolvePostedPurchaseInvoice($correctedInvoice);

            if ($correctedInvoice && ! $postedCorrectedInvoice) {
                throw ValidationException::withMessages([
                    'corrects_invoice_id' => 'The selected purchase invoice has not been posted yet.',
                ]);
            }

            if ($postedCorrectedInvoice) {
                $this->validateCreditQuantitiesAgainstInvoice($correctedInvoice, $data);
                $this->ensureInvoiceLinkedMemoHasExactLineage($postedCorrectedInvoice, $data->lines->toArray());
            }

            $memo->update([
                'business_id' => $data->business_id ?? $memo->business_id,
                'vendor_id' => $data->vendor_id,
                'vendor_name' => $vendor->vendor_name,
                'corrects_invoice_id' => $data->corrects_invoice_id,
                'corrects_invoice_number' => $correctedInvoice?->document_number,
                'external_document_number' => $data->external_document_number,
                'posting_date' => $data->posting_date ?? $correctedInvoice?->posting_date ?? now(),
                'document_date' => $data->document_date ?? $correctedInvoice?->document_date ?? now(),
                'location_id' => $data->location_id ?? $correctedInvoice?->location_id,
                'currency_code' => $data->currency_code ?? $correctedInvoice?->currency_code ?? $memo->currency_code,
                'reason_code' => $data->reason_code,
                'description' => $data->description,
            ]);

            $memo->lines()->delete();

            foreach ($data->lines as $index => $line) {
                $item = Item::findOrFail($line->item_id);
                $memo->lines()->create([
                    'line_number' => ($index + 1) * 10000,
                    'item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'description' => $line->description ?? $item->description,
                    'corrected_invoice_line_id' => $this->resolveCorrectedInvoiceLineId($correctedInvoice, $line),
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'tax_percent' => $line->tax_percent,
                    'general_product_posting_group_id' => $item->general_product_posting_group_id,
                    'unit_of_measure_code' => $item->uom_code ?? 'EA',
                ]);
            }

            return $memo;
        });
    }

    public function submitForApproval(PurchaseCreditMemo $memo): void
    {
        $memo->submitForApproval();
    }

    public function approve(PurchaseCreditMemo $memo, int $userId): void
    {
        $memo->approve($userId);
    }

    public function reject(PurchaseCreditMemo $memo, int $userId, string $reason): void
    {
        $memo->reject($userId, $reason);
    }

    /**
     * @throws \Throwable
     */
    public function post(PurchaseCreditMemo $memo): PostedPurchaseCreditMemo
    {
        $userId = Auth::id();

        if (! $userId) {
            throw new \RuntimeException('Unauthenticated user');
        }

        return DB::transaction(function () use ($memo, $userId) {
            $memo = PurchaseCreditMemo::query()
                ->with(['lines.item', 'lines.correctedInvoiceLine', 'vendor', 'location', 'correctedInvoice'])
                ->lockForUpdate()
                ->findOrFail($memo->id);

            Gate::forUser(User::query()->findOrFail($userId))->authorize('post', $memo);

            if ($memo->status === ApprovalStatus::POSTED || PostedPurchaseCreditMemo::query()->where('document_number', $memo->document_number)->exists()) {
                throw new \RuntimeException('Purchase credit memo is already posted.');
            }

            $approvalRequired = $this->approvalTemplateService->requiresApproval($memo);

            if ($approvalRequired && $memo->status !== ApprovalStatus::APPROVED) {
                throw ValidationException::withMessages(['status' => 'Only approved credit memos can be posted.']);
            }

            if ($memo->lines->isEmpty()) {
                throw new \RuntimeException('No lines to post for this purchase credit memo.');
            }

            $correctedInvoice = $this->resolveCorrectedPostedInvoice($memo);

            if ($correctedInvoice) {
                $this->validateCreditQuantitiesAgainstPostedInvoice($correctedInvoice, $memo);
            }

            $subtotal = $memo->lines->sum(
                fn (PurchaseCreditMemoLine $line): float => (float) $line->quantity * (float) $line->unit_cost
            );
            $taxAmount = $memo->lines->sum(
                fn (PurchaseCreditMemoLine $line): float => (float) ($line->tax_amount ?? ($line->quantity * $line->unit_cost * ($line->tax_percent / 100)))
            );
            $grandTotal = $subtotal + $taxAmount;

            $memo->forceFill([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
            ])->save();

            $postedMemo = PostedPurchaseCreditMemo::create([
                'business_id' => $memo->business_id ?? (request()?->integer('business_id') ?: session('active_business_id')),
                'document_number' => $memo->document_number,
                'external_document_number' => $memo->external_document_number,
                'vendor_id' => $memo->vendor_id,
                'vendor_name' => $memo->vendor_name,
                'vendor_address' => $memo->vendor?->address,
                'vendor_city' => $memo->vendor?->city,
                'vendor_post_code' => $memo->vendor?->postal_code,
                'vendor_country' => $memo->vendor?->country,
                'vendor_tax_registration_number' => $memo->vendor?->tax_id,
                'posting_date' => $memo->posting_date,
                'document_date' => $memo->document_date,
                'vendor_posting_group_id' => $memo->vendor->vendor_posting_group_id,
                'general_business_posting_group_id' => $memo->vendor->general_business_posting_group_id,
                'currency_code' => $memo->currency_code,
                'currency_factor' => 1,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'corrects_invoice_id' => $memo->corrects_invoice_id,
                'corrects_invoice_number' => $memo->corrects_invoice_number,
                'source_document_id' => $memo->id,
                'source_document_type' => PurchaseCreditMemo::class,
                'reason_code' => $memo->reason_code,
                'description' => $memo->description,
                'location_code' => $memo->location?->code,
            ]);

            foreach ($memo->lines as $line) {
                $itemLedgerEntry = $this->createItemLedgerEntryForLine($postedMemo, $memo, $line);

                PostedPurchaseCreditMemoLine::create([
                    'credit_memo_id' => $postedMemo->id,
                    'line_number' => $line->line_number,
                    'type' => 'ITEM',
                    'item_id' => $line->item_id,
                    'gl_account_id' => null,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_of_measure' => $line->unit_of_measure_code,
                    'unit_price' => $line->unit_cost,
                    'discount_percent' => 0,
                    'discount_amount' => 0,
                    'amount' => $line->line_total,
                    'tax_percent' => $line->tax_percent,
                    'tax_amount' => $line->tax_amount,
                    'line_total' => $line->grand_total,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->item?->inventory_posting_group_id,
                    'corrected_invoice_line_id' => $line->corrected_invoice_line_id,
                ]);
            }

            $this->postingService->postPurchaseCreditMemo($memo);

            $memo->update(['status' => ApprovalStatus::POSTED]);

            $ledgerEntryExists = VendorLedgerEntry::query()
                ->where('document_type', 'PURCHASE_CREDIT_MEMO')
                ->where('document_number', $postedMemo->document_number)
                ->where('vendor_id', $postedMemo->vendor_id)
                ->exists();

            if (! $ledgerEntryExists) {
                VendorLedgerEntry::createFromCreditMemo($postedMemo);
            }

            return $postedMemo;
        });
    }

    private function resolveCorrectedPostedInvoice(PurchaseCreditMemo $memo): ?PostedPurchaseInvoice
    {
        if (! $memo->corrects_invoice_id && ! $memo->corrects_invoice_number) {
            return null;
        }

        if ($memo->corrects_invoice_id) {
            $purchaseInvoice = PurchaseInvoice::query()->find($memo->corrects_invoice_id);

            if ($purchaseInvoice) {
                return $this->resolvePostedPurchaseInvoice($purchaseInvoice);
            }
        }

        $invoiceNumber = $memo->corrects_invoice_number ?: $memo->correctedInvoice?->document_number;

        if (! $invoiceNumber) {
            return null;
        }

        $purchaseInvoice = PurchaseInvoice::query()
            ->where('document_number', $invoiceNumber)
            ->first();

        return $purchaseInvoice ? $this->resolvePostedPurchaseInvoice($purchaseInvoice) : null;
    }

    private function resolvePostedPurchaseInvoice(PurchaseInvoice $purchaseInvoice): ?PostedPurchaseInvoice
    {
        return PostedPurchaseInvoice::query()
            ->where('document_number', $purchaseInvoice->document_number)
            ->where('vendor_id', $purchaseInvoice->vendor_id)
            ->first();
    }

    private function validateCreditQuantitiesAgainstPostedInvoice(PostedPurchaseInvoice $correctedInvoice, PurchaseCreditMemo $memo): void
    {
        $correctedInvoice->loadMissing('lines');
        $memo->loadMissing('lines.item', 'lines.correctedInvoiceLine');

        $this->lockCanonicalPostedInvoiceLines($correctedInvoice, $memo);

        if ($memo->lines->every(fn (PurchaseCreditMemoLine $line): bool => filled($line->corrected_invoice_line_id))) {
            $this->validateCreditQuantitiesAgainstPostedInvoiceLines($correctedInvoice, $memo);

            return;
        }

        $this->validateCreditQuantitiesAgainstPostedInvoiceItems($correctedInvoice, $memo);
    }

    private function validateCreditQuantitiesAgainstPostedInvoiceLines(PostedPurchaseInvoice $correctedInvoice, PurchaseCreditMemo $memo): void
    {
        $postedLineById = $correctedInvoice->lines->keyBy('id');
        $invoiceLinkConstraints = $this->postedCreditMemoInvoiceLinkConstraints($memo, $correctedInvoice);

        $alreadyCreditedByLine = PostedPurchaseCreditMemoLine::query()
            ->join('posted_purchase_credit_memos as headers', 'headers.id', '=', 'posted_purchase_credit_memo_lines.credit_memo_id')
            ->where($invoiceLinkConstraints)
            ->whereNotNull('posted_purchase_credit_memo_lines.corrected_invoice_line_id')
            ->groupBy('posted_purchase_credit_memo_lines.corrected_invoice_line_id')
            ->selectRaw('posted_purchase_credit_memo_lines.corrected_invoice_line_id, COALESCE(SUM(ABS(posted_purchase_credit_memo_lines.quantity)), 0) as quantity')
            ->pluck('quantity', 'corrected_invoice_line_id');

        foreach ($memo->lines as $line) {
            $invoiceLineId = (int) ($line->corrected_invoice_line_id ?? 0);
            $postedLine = $postedLineById->get($invoiceLineId);

            if (! $postedLine) {
                throw ValidationException::withMessages([
                    'lines' => 'Credit line references an invoice line that does not belong to the selected posted invoice.',
                ]);
            }

            $requestedQuantity = abs((float) $line->quantity);
            $invoicedQuantity = abs((float) $postedLine->quantity);
            $alreadyCreditedQuantity = (float) ($alreadyCreditedByLine[$invoiceLineId] ?? 0.0);
            $availableQuantity = max(0.0, $invoicedQuantity - $alreadyCreditedQuantity);

            if ($requestedQuantity > ($availableQuantity + 0.000001)) {
                throw ValidationException::withMessages([
                    'lines' => "Credit quantity for invoice line {$postedLine->line_number} exceeds remaining returnable quantity. Available: {$availableQuantity}, requested: {$requestedQuantity}.",
                ]);
            }
        }
    }

    private function validateCreditQuantitiesAgainstPostedInvoiceItems(PostedPurchaseInvoice $correctedInvoice, PurchaseCreditMemo $memo): void
    {
        $invoiceLinkConstraints = $this->postedCreditMemoInvoiceLinkConstraints($memo, $correctedInvoice);

        $invoicedQuantityByItem = $correctedInvoice->lines
            ->groupBy('item_id')
            ->map(fn ($lines): float => abs((float) $lines->sum('quantity')));

        $alreadyCreditedByItem = PostedPurchaseCreditMemoLine::query()
            ->join('posted_purchase_credit_memos as headers', 'headers.id', '=', 'posted_purchase_credit_memo_lines.credit_memo_id')
            ->where($invoiceLinkConstraints)
            ->groupBy('posted_purchase_credit_memo_lines.item_id')
            ->selectRaw('posted_purchase_credit_memo_lines.item_id, COALESCE(SUM(ABS(posted_purchase_credit_memo_lines.quantity)), 0) as quantity')
            ->pluck('quantity', 'item_id');

        $requestedQuantityByItem = $memo->lines
            ->groupBy('item_id')
            ->map(fn ($lines): float => abs((float) $lines->sum('quantity')));

        foreach ($requestedQuantityByItem as $itemId => $requestedQuantity) {
            $invoicedQuantity = (float) ($invoicedQuantityByItem[$itemId] ?? 0.0);
            $alreadyCreditedQuantity = (float) ($alreadyCreditedByItem[$itemId] ?? 0.0);
            $availableQuantity = max(0.0, $invoicedQuantity - $alreadyCreditedQuantity);

            if ($requestedQuantity > ($availableQuantity + 0.000001)) {
                $itemCode = $memo->lines->firstWhere('item_id', $itemId)?->item?->item_code ?? ('#'.$itemId);

                throw ValidationException::withMessages([
                    'lines' => "Credit quantity for item {$itemCode} exceeds invoiced quantity. Available: {$availableQuantity}, requested: {$requestedQuantity}.",
                ]);
            }
        }
    }

    private function lockCanonicalPostedInvoiceLines(PostedPurchaseInvoice $correctedInvoice, PurchaseCreditMemo $memo): void
    {
        $lineIds = $memo->lines
            ->pluck('corrected_invoice_line_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();

        if ($lineIds->isEmpty()) {
            $correctedInvoice->lines()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return;
        }

        $lockedLineIds = $correctedInvoice->lines()
            ->whereIn('id', $lineIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($lockedLineIds->count() !== $lineIds->count()) {
            throw ValidationException::withMessages([
                'lines' => 'Credit line references an invoice line that does not belong to the selected posted invoice.',
            ]);
        }
    }

    /**
     * @return \Closure(Builder): void
     */
    private function postedCreditMemoInvoiceLinkConstraints(PurchaseCreditMemo $memo, PostedPurchaseInvoice $correctedInvoice): \Closure
    {
        return function ($query) use ($memo, $correctedInvoice): void {
            $query->where(function ($linkQuery) use ($memo, $correctedInvoice): void {
                $linkQuery
                    ->where('headers.corrects_invoice_id', $memo->corrects_invoice_id)
                    ->orWhere('headers.corrects_invoice_number', $memo->corrects_invoice_number ?: $correctedInvoice->document_number);
            });
        };
    }

    private function resolvePurchaseInvoice(?int $purchaseInvoiceId, ?string $documentNumber): ?PurchaseInvoice
    {
        if ($purchaseInvoiceId) {
            $invoice = PurchaseInvoice::query()->find($purchaseInvoiceId);

            if ($invoice) {
                return $invoice;
            }
        }

        if ($documentNumber) {
            $invoice = PurchaseInvoice::query()->where('document_number', $documentNumber)->first();

            if ($invoice) {
                return $invoice;
            }
        }

        return null;
    }

    private function resolveCorrectedInvoiceLineId(?PostedPurchaseInvoice $invoice, PurchaseCreditMemoLineData $line): ?int
    {
        if (! $invoice) {
            return null;
        }

        $invoiceLineId = (int) ($line->corrected_invoice_line_id ?? 0);

        if ($invoiceLineId <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'Invoice-linked purchase credit memos require an exact posted invoice line reference for every line.',
            ]);
        }

        $invoiceLine = $invoice->lines()->whereKey($invoiceLineId)->first();

        if (! $invoiceLine) {
            throw ValidationException::withMessages([
                'lines' => 'Credit line references an invoice line that does not belong to the selected posted invoice.',
            ]);
        }

        if ((int) $invoiceLine->item_id !== (int) $line->item_id) {
            throw ValidationException::withMessages([
                'lines' => 'Credit line item does not match the selected posted invoice line.',
            ]);
        }

        return $invoiceLine->id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function ensureInvoiceLinkedMemoHasExactLineage(PostedPurchaseInvoice $invoice, array $lines): void
    {
        $invoiceLinesById = $invoice->lines()->get()->keyBy('id');

        foreach ($lines as $line) {
            $invoiceLineId = (int) ($line['corrected_invoice_line_id'] ?? 0);

            if ($invoiceLineId <= 0) {
                throw ValidationException::withMessages([
                    'lines' => 'Invoice-linked purchase credit memos require an exact posted invoice line reference for every line.',
                ]);
            }

            $invoiceLine = $invoiceLinesById->get($invoiceLineId);

            if (! $invoiceLine) {
                throw ValidationException::withMessages([
                    'lines' => 'Credit line references an invoice line that does not belong to the selected posted invoice.',
                ]);
            }

            $lineItemId = (int) ($line['item_id'] ?? 0);
            if ($lineItemId > 0 && (int) $invoiceLine->item_id !== $lineItemId) {
                throw ValidationException::withMessages([
                    'lines' => 'Credit line item does not match the selected posted invoice line.',
                ]);
            }
        }
    }

    private function createItemLedgerEntryForLine(
        PostedPurchaseCreditMemo $postedMemo,
        PurchaseCreditMemo $memo,
        PurchaseCreditMemoLine $line
    ): ?ItemLedgerEntry {
        $item = $line->item;

        if (! $item || ! $item->isInventoryItem()) {
            return null;
        }

        $quantityBase = $this->quantityBase($line, $item);

        if ($quantityBase <= 0) {
            throw new \RuntimeException("Quantity must be greater than zero for item {$item->item_code}");
        }

        if ((float) $item->ledger_on_hand < $quantityBase) {
            throw new \RuntimeException("Insufficient stock for item: {$item->description}");
        }

        $locationId = $memo->location_id ?? $item->location_id;

        if (! $locationId) {
            throw new \RuntimeException("Location is missing for item {$item->item_code} on purchase credit memo {$memo->document_number}.");
        }

        $costAmount = (float) $line->line_total;

        $entry = ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::PURCHASE,
            'document_type' => 'PURCHASE_CREDIT_MEMO',
            'document_line_number' => $line->line_number ?? $line->id,
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => -$quantityBase,
            'remaining_quantity' => 0,
            'open' => false,
            'posting_date' => $memo->posting_date,
            'entry_date' => now(),
            'document_number' => $memo->document_number,
            'source_id' => $postedMemo->id,
            'source_type' => PostedPurchaseCreditMemo::class,
            'cost_amount_actual' => $costAmount,
            'cost_amount_expected' => 0,
            'purchase_amount_actual' => $costAmount,
            'general_business_posting_group_id' => $postedMemo->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
        ]);

        app(ItemApplicationService::class)->applyOutbound($entry, 'purchase_credit_memo', strict: false);
        $this->assertValueEntryCreated($entry, $memo->corrects_invoice_number ?: $memo->correctedInvoice?->document_number, $memo->correctedInvoice?->posting_date);
        app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($entry);

        $item->decrement('inventory', $quantityBase);

        return $entry;
    }

    private function quantityBase(PurchaseCreditMemoLine $line, Item $item): float
    {
        $conversionFactor = $item->getConversionFactorForUom($line->unit_of_measure_code ?: $item->base_unit_of_measure);

        return abs((float) $line->quantity) * ($conversionFactor > 0 ? $conversionFactor : 1.0);
    }

    private function assertValueEntryCreated(
        ItemLedgerEntry $entry,
        ?string $originalDocumentNumber = null,
        mixed $originalPostingDate = null
    ): void {
        $valueEntry = ValueEntry::query()
            ->where('item_ledger_entry_no', $entry->entry_number)
            ->where('document_no', $entry->document_number)
            ->where('document_line_no', $entry->document_line_number)
            ->first();

        if (! $valueEntry) {
            throw new \RuntimeException("Value Entry was not created for item ledger entry {$entry->entry_number}.");
        }

        $valueEntry->forceFill([
            'original_document_no' => $originalDocumentNumber,
            'original_posting_date' => $originalPostingDate,
        ])->save();
    }

    private function validateCreditQuantitiesAgainstInvoice(PurchaseInvoice $invoice, PurchaseCreditMemoData $data): void
    {
        $invoice->loadMissing('lines');

        $maxByItemId = $invoice->lines
            ->groupBy('item_id')
            ->map(fn ($lines) => (float) $lines->sum('quantity'));

        $requestedByItemId = collect($data->lines->toArray())
            ->groupBy('item_id')
            ->map(fn ($lines) => (float) collect($lines)->sum('quantity'));

        foreach ($requestedByItemId as $itemId => $requestedQty) {
            $maxQty = (float) ($maxByItemId[$itemId] ?? 0.0);

            if ($requestedQty > ($maxQty + 0.000001)) {
                $itemCode = Item::query()->whereKey($itemId)->value('item_code') ?? ('#'.$itemId);
                throw ValidationException::withMessages([
                    'lines' => "Credit quantity for item {$itemCode} exceeds invoiced quantity. Max: {$maxQty}, requested: {$requestedQty}.",
                ]);
            }
        }
    }
}
