<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Data\Sales\SalesCreditMemoData;
use App\Data\Sales\SalesCreditMemoLineData;
use App\Enums\ApprovalStatus;
use App\Enums\ItemLedgerEntryType;
use App\Exceptions\BusinessException;
use App\Exceptions\DocumentStateException;
use App\Exceptions\NumberSeriesException;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesCreditMemoLine;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Inventory\ReturnCostApplicationService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Services\PostingService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SalesCreditMemoService
{
    public function __construct(
        protected PostingService $postingService
    ) {}

    /**
     * @throws \Throwable
     */
    public function create(SalesCreditMemoData $data): SalesCreditMemo
    {
        $this->validateCreditMemoData($data);

        return DB::transaction(function () use ($data) {
            $creditMemo = SalesCreditMemo::create([
                'customer_id' => $data->customer_id,
                'sales_invoice_id' => $data->sales_invoice_id,
                'posted_sales_invoice_id' => $data->posted_sales_invoice_id,
                'memo_number' => $data->memo_number ?? $this->generateMemoNumber(),
                'status' => ApprovalStatus::DRAFT,
                'reason' => $data->reason,
                'effective_date' => $data->effective_date ?? now(),
                'currency_code' => $data->currency_code,
                'total_amount' => 0,
            ]);

            foreach ($data->items as $line) {
                $postedInvoiceLine = $this->postedInvoiceLineForData($data, $line);
                $item = Item::query()->findOrFail($postedInvoiceLine?->item_id ?? $line->item_id);

                $creditMemo->items()->create($this->linePayload($line, $item, $postedInvoiceLine));
            }

            $creditMemo->refreshTotal();

            return $creditMemo;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(
        SalesCreditMemoLineData $line,
        Item $item,
        ?PostedSalesInvoiceLine $postedInvoiceLine = null
    ): array {
        $quantity = $line->quantity;
        $unitPrice = $postedInvoiceLine ? abs((float) $postedInvoiceLine->unit_price) : $line->unit_price;
        $vatPercent = $postedInvoiceLine ? (float) $postedInvoiceLine->vat_percentage : $line->vat_percent;
        $lineDiscountAmount = $postedInvoiceLine ? abs((float) $postedInvoiceLine->line_discount_amount) : $line->line_discount_amount;
        $lineDiscountPercent = $postedInvoiceLine ? (float) $postedInvoiceLine->line_discount_percent : $line->line_discount_percent;

        $lineTotal = $quantity * $unitPrice;
        $discountAmount = $lineDiscountAmount > 0
            ? $lineDiscountAmount
            : ($lineTotal * ($lineDiscountPercent / 100));
        $amount = max(0, $lineTotal - $discountAmount);
        $vatAmount = round($amount * ($vatPercent / 100), 2);

        return [
            'item_id' => $item->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_percent' => $vatPercent,
            'description' => $postedInvoiceLine?->item_description ?? $line->description ?? $item->description,
            'unit_of_measure_code' => $postedInvoiceLine?->unit_of_measure_code ?: ($line->unit_of_measure_code ?: $item->base_unit_of_measure),
            'line_discount_percent' => $lineDiscountPercent,
            'line_discount_amount' => $lineDiscountAmount,
            'amount' => $amount,
            'vat_amount' => $vatAmount,
            'amount_including_vat' => $amount + $vatAmount,
            'sales_invoice_line_id' => $line->sales_invoice_line_id,
            'posted_sales_invoice_line_id' => $postedInvoiceLine?->id,
        ];
    }

    /**
     * @throws \Throwable
     */
    public function update(SalesCreditMemo $creditMemo, SalesCreditMemoData $data): SalesCreditMemo
    {
        if ($creditMemo->status !== ApprovalStatus::DRAFT) {
            throw new DocumentStateException('Only draft credit memos can be modified.');
        }

        $this->validateCreditMemoData($data);

        return DB::transaction(function () use ($creditMemo, $data) {
            $creditMemo->update([
                'customer_id' => $data->customer_id,
                'sales_invoice_id' => $data->sales_invoice_id,
                'posted_sales_invoice_id' => $data->posted_sales_invoice_id,
                'reason' => $data->reason,
                'effective_date' => $data->effective_date ?? now(),
                'currency_code' => $data->currency_code,
            ]);

            $creditMemo->items()->delete();

            foreach ($data->items as $line) {
                $postedInvoiceLine = $this->postedInvoiceLineForData($data, $line);
                $item = Item::query()->findOrFail($postedInvoiceLine?->item_id ?? $line->item_id);

                $creditMemo->items()->create($this->linePayload($line, $item, $postedInvoiceLine));
            }

            $creditMemo->refreshTotal();

            return $creditMemo;
        });
    }

    public function submitForApproval(SalesCreditMemo $creditMemo): void
    {
        $creditMemo->submitForApproval();
    }

    public function approve(SalesCreditMemo $creditMemo, int $userId): void
    {
        $creditMemo->approve($userId);
    }

    public function reject(SalesCreditMemo $creditMemo, int $userId, string $reason): void
    {
        $creditMemo->reject($userId, $reason);
    }

    /**
     * @throws \Throwable
     */
    public function post(SalesCreditMemo $creditMemo): void
    {
        $userId = Auth::id();

        if (! $userId) {
            throw new AuthenticationException('Authenticated user is required to post a sales credit memo.');
        }

        Gate::forUser(User::query()->findOrFail($userId))->authorize('post', $creditMemo);

        if ($creditMemo->isPosted()) {
            throw new DocumentStateException('Sales credit memo is already posted.');
        }

        if ($creditMemo->status !== ApprovalStatus::APPROVED) {
            throw new DocumentStateException('Only approved credit memos can be posted.');
        }

        DB::transaction(function () use ($creditMemo) {
            $creditMemo->loadMissing(['items.item', 'items.postedInvoiceLine', 'customer', 'invoice', 'postedInvoice']);

            if ($creditMemo->items->isEmpty()) {
                throw new BusinessException('No lines to post for this sales credit memo.', field: 'items');
            }

            $correctedPostedInvoice = $this->resolveCorrectedPostedInvoice($creditMemo);

            if ($correctedPostedInvoice) {
                $this->validateCreditQuantitiesAgainstPostedInvoice($correctedPostedInvoice, $creditMemo);
            }

            $customer = $creditMemo->customer;

            $postedMemo = PostedSalesCreditMemo::create([
                'document_number' => $creditMemo->memo_number,
                'customer_id' => $creditMemo->customer_id,
                'customer_name' => $customer->name,
                'customer_address' => $customer->address,
                'customer_posting_group_id' => $customer->customer_posting_group_id,
                'general_business_posting_group_id' => $customer->general_business_posting_group_id,
                'posting_date' => $creditMemo->effective_date ?? now(),
                'document_date' => $creditMemo->effective_date ?? now(),
                'currency_code' => $creditMemo->currency_code ?? 'NGN',
                'currency_factor' => 1,
                'total_amount' => $creditMemo->total_amount,
                'grand_total' => $creditMemo->total_amount,
                'remaining_amount' => abs((float) $creditMemo->total_amount),
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'corrected_invoice_id' => $correctedPostedInvoice?->id,
                'corrected_invoice_number' => $correctedPostedInvoice?->document_number ?? $creditMemo->invoice?->invoice_number,
                'return_reason_comment' => $creditMemo->reason,
            ]);

            $lineNumber = 0;
            $subtotal = 0.0;
            $totalVat = 0.0;

            foreach ($creditMemo->items as $line) {
                $lineNumber += 10000;
                $item = $line->item;
                $quantity = abs((float) $line->quantity);
                $conversionFactor = $item ? $this->conversionFactor($item, $line->unit_of_measure_code) : 1.0;
                $quantityBase = $item ? $this->quantityBase($line, $item) : $quantity;
                $lineAmount = (float) ($line->amount ?? ($quantity * (float) $line->unit_price));
                $vatAmount = (float) ($line->vat_amount ?? 0);
                $amountIncludingVat = (float) ($line->amount_including_vat ?? ($lineAmount + $vatAmount));
                $unitCost = (float) ($item?->unit_cost ?? 0);
                $costAmount = $quantityBase * $unitCost;
                $itemLedgerEntry = $this->createItemLedgerEntryForLine($postedMemo, $line);

                PostedSalesCreditMemoLine::create([
                    'posted_sales_credit_memo_id' => $postedMemo->id,
                    'line_number' => $line->line_no ?: $lineNumber,
                    'corrected_invoice_line_id' => $this->correctedInvoiceLineId($correctedPostedInvoice, $line),
                    'item_id' => $line->item_id,
                    'item_code' => $item->item_code,
                    'item_description' => $item->description,
                    'posting_date' => $postedMemo->posting_date,
                    'general_product_posting_group_id' => $item->general_product_posting_group_id,
                    'inventory_posting_group_id' => $item->inventory_posting_group_id,
                    'quantity' => -$quantity,
                    'unit_of_measure_code' => $line->unit_of_measure_code ?: $item->base_unit_of_measure,
                    'qty_per_unit_of_measure' => $conversionFactor,
                    'quantity_base' => -$quantityBase,
                    'unit_price' => $line->unit_price,
                    'unit_cost' => $unitCost,
                    'unit_cost_lcy' => $unitCost,
                    'line_discount_percent' => (float) $line->line_discount_percent,
                    'line_discount_amount' => (float) $line->line_discount_amount,
                    'line_total' => -($quantity * (float) $line->unit_price),
                    'line_amount' => -$lineAmount,
                    'vat_percentage' => (float) $line->vat_percent,
                    'vat_amount' => -$vatAmount,
                    'amount_including_vat' => -$amountIncludingVat,
                    'cost_amount_reversed' => $costAmount,
                    'inventory_amount_reversed' => $costAmount,
                    'return_type' => 'FULL',
                    'item_ledger_entry_id' => $itemLedgerEntry?->id,
                ]);

                $subtotal += $lineAmount;
                $totalVat += $vatAmount;
            }

            $this->postingService->postSalesCreditMemo($creditMemo);

            $postedMemo->update([
                'subtotal' => -$subtotal,
                'total_amount' => -$subtotal,
                'total_vat' => -$totalVat,
                'grand_total' => -($subtotal + $totalVat),
                'remaining_amount' => $subtotal + $totalVat,
            ]);

            $ledgerEntryExists = CustomerLedgerEntry::query()
                ->where('document_type', 'SALES_CREDIT_MEMO')
                ->where('document_number', $postedMemo->document_number)
                ->where('customer_id', $postedMemo->customer_id)
                ->exists();

            if (! $ledgerEntryExists) {
                CustomerLedgerEntry::createFromCreditMemo($postedMemo);
            }

            $creditMemo->update([
                'status' => ApprovalStatus::POSTED,
                'posted_by' => Auth::id(),
            ]);
        });
    }

    private function resolveCorrectedPostedInvoice(SalesCreditMemo $creditMemo): ?PostedSalesInvoice
    {
        if ($creditMemo->posted_sales_invoice_id) {
            return PostedSalesInvoice::query()->find($creditMemo->posted_sales_invoice_id);
        }

        if (! $creditMemo->sales_invoice_id) {
            return null;
        }

        $invoiceNumber = $creditMemo->invoice?->invoice_number;

        if (! $invoiceNumber) {
            return null;
        }

        return PostedSalesInvoice::query()
            ->where('document_number', $invoiceNumber)
            ->first();
    }

    private function validateCreditQuantitiesAgainstPostedInvoice(PostedSalesInvoice $postedInvoice, SalesCreditMemo $creditMemo): void
    {
        $postedInvoice->loadMissing('lines');
        $creditMemo->loadMissing('items.item', 'items.postedInvoiceLine');

        if ((int) $postedInvoice->customer_id !== (int) $creditMemo->customer_id) {
            throw ValidationException::withMessages([
                'posted_sales_invoice_id' => 'The selected posted invoice does not belong to the credit memo customer.',
            ]);
        }

        if ($postedInvoice->cancelled) {
            throw ValidationException::withMessages([
                'posted_sales_invoice_id' => 'Cancelled posted invoices cannot be credited.',
            ]);
        }

        if ($creditMemo->posted_sales_invoice_id) {
            $this->validateCreditQuantitiesAgainstPostedInvoiceLines($postedInvoice, $creditMemo);

            return;
        }

        $invoicedQuantityByItem = $postedInvoice->lines
            ->groupBy('item_id')
            ->map(fn ($lines): float => abs((float) $lines->sum('quantity')));

        $alreadyCreditedByItem = PostedSalesCreditMemoLine::query()
            ->join('posted_sales_credit_memos as headers', 'headers.id', '=', 'posted_sales_credit_memo_lines.posted_sales_credit_memo_id')
            ->where('headers.corrected_invoice_id', $postedInvoice->id)
            ->groupBy('posted_sales_credit_memo_lines.item_id')
            ->selectRaw('posted_sales_credit_memo_lines.item_id, COALESCE(SUM(ABS(posted_sales_credit_memo_lines.quantity)), 0) as quantity')
            ->pluck('quantity', 'item_id');

        $requestedQuantityByItem = $creditMemo->items
            ->groupBy('item_id')
            ->map(fn ($lines): float => abs((float) $lines->sum('quantity')));

        foreach ($requestedQuantityByItem as $itemId => $requestedQuantity) {
            $invoicedQuantity = (float) ($invoicedQuantityByItem[$itemId] ?? 0.0);
            $alreadyCreditedQuantity = (float) ($alreadyCreditedByItem[$itemId] ?? 0.0);
            $availableQuantity = max(0.0, $invoicedQuantity - $alreadyCreditedQuantity);

            if ($requestedQuantity > ($availableQuantity + 0.000001)) {
                $itemCode = $creditMemo->items->firstWhere('item_id', $itemId)?->item?->item_code ?? ('#'.$itemId);

                throw ValidationException::withMessages([
                    'items' => "Credit quantity for item {$itemCode} exceeds invoiced quantity. Available: {$availableQuantity}, requested: {$requestedQuantity}.",
                ]);
            }
        }
    }

    private function validateCreditQuantitiesAgainstPostedInvoiceLines(PostedSalesInvoice $postedInvoice, SalesCreditMemo $creditMemo): void
    {
        foreach ($creditMemo->items as $line) {
            if (! $line->posted_sales_invoice_line_id) {
                throw ValidationException::withMessages([
                    'items' => 'Each line on an invoice-linked sales credit memo must reference an original posted invoice line.',
                ]);
            }

            $postedInvoiceLine = $line->postedInvoiceLine;

            if (! $postedInvoiceLine || (int) $postedInvoiceLine->posted_sales_invoice_id !== (int) $postedInvoice->id) {
                throw ValidationException::withMessages([
                    'items' => 'A selected credit memo line does not belong to the linked posted invoice.',
                ]);
            }

            if ((int) $postedInvoiceLine->item_id !== (int) $line->item_id) {
                throw ValidationException::withMessages([
                    'items' => 'A selected credit memo item does not match its original posted invoice line.',
                ]);
            }

            $requestedQuantity = abs((float) $line->quantity);
            $availableQuantity = $this->remainingReturnableQuantityForPostedInvoiceLine($postedInvoiceLine);

            if ($requestedQuantity > ($availableQuantity + 0.000001)) {
                $itemCode = $postedInvoiceLine->item_code ?? $line->item?->item_code ?? ('#'.$line->item_id);

                throw ValidationException::withMessages([
                    'items' => "Credit quantity for invoice line {$itemCode} exceeds remaining returnable quantity. Available: {$availableQuantity}, requested: {$requestedQuantity}.",
                ]);
            }
        }
    }

    private function correctedInvoiceLineId(?PostedSalesInvoice $postedInvoice, SalesCreditMemoLine $line): ?int
    {
        if ($line->posted_sales_invoice_line_id) {
            return (int) $line->posted_sales_invoice_line_id;
        }

        if ($line->sales_invoice_line_id) {
            return (int) $line->sales_invoice_line_id;
        }

        return $postedInvoice?->lines
            ->firstWhere('item_id', $line->item_id)
            ?->id;
    }

    private function createItemLedgerEntryForLine(PostedSalesCreditMemo $postedMemo, SalesCreditMemoLine $line): ?ItemLedgerEntry
    {
        $item = $line->item;

        if (! $item || ! $item->isInventoryItem()) {
            return null;
        }

        $quantityBase = $this->quantityBase($line, $item);

        if ($quantityBase <= 0) {
            throw new BusinessException("Quantity must be greater than zero for item {$item->item_code}", field: 'items');
        }

        $locationId = $postedMemo->location_id ?? $item->location_id ?? $postedMemo->customer?->location_id;

        if (! $locationId) {
            throw new BusinessException("Location is missing for item {$item->item_code} on sales credit memo {$postedMemo->document_number}.", field: 'location_id');
        }

        $costAmount = $quantityBase * (float) ($item->unit_cost ?? 0);

        $entry = ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::SALE,
            'document_type' => 'SALES_CREDIT_MEMO',
            'document_line_number' => $line->line_no ?: $line->id,
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => $quantityBase,
            'remaining_quantity' => $quantityBase,
            'open' => true,
            'posting_date' => $postedMemo->posting_date,
            'entry_date' => now(),
            'document_number' => $postedMemo->document_number,
            'source_id' => $postedMemo->id,
            'source_type' => PostedSalesCreditMemo::class,
            'cost_amount_actual' => $costAmount,
            'cost_amount_expected' => 0,
            'general_business_posting_group_id' => $postedMemo->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
        ]);

        $originalOutboundEntry = $this->originalOutboundEntryForReturn($postedMemo, $line);
        app(ReturnCostApplicationService::class)->applyExactOrFallbackCost($entry, $originalOutboundEntry);
        $this->assertValueEntryCreated($entry, $postedMemo->corrected_invoice_number, $postedMemo->posting_date);
        app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($entry);

        $item->increment('inventory', $quantityBase);

        return $entry;
    }

    private function originalOutboundEntryForReturn(PostedSalesCreditMemo $postedMemo, SalesCreditMemoLine $line): ?ItemLedgerEntry
    {
        $postedInvoiceLine = null;

        if ($line->posted_sales_invoice_line_id) {
            $postedInvoiceLine = PostedSalesInvoiceLine::query()->find($line->posted_sales_invoice_line_id);
        }

        if (! $postedInvoiceLine && $postedMemo->corrected_invoice_id) {
            $postedInvoiceLine = PostedSalesInvoiceLine::query()
                ->where('posted_sales_invoice_id', $postedMemo->corrected_invoice_id)
                ->where('item_id', $line->item_id)
                ->first();
        }

        if (! $postedInvoiceLine?->item_ledger_entry_id) {
            return null;
        }

        return ItemLedgerEntry::query()->find($postedInvoiceLine->item_ledger_entry_id);
    }

    private function quantityBase(SalesCreditMemoLine $line, Item $item): float
    {
        return abs((float) $line->quantity) * $this->conversionFactor($item, $line->unit_of_measure_code);
    }

    private function conversionFactor(Item $item, ?string $unitOfMeasureCode): float
    {
        $conversionFactor = $item->getConversionFactorForUom($unitOfMeasureCode ?: $item->base_unit_of_measure);

        return $conversionFactor > 0 ? $conversionFactor : 1.0;
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

    private function validateCreditMemoData(SalesCreditMemoData $data): void
    {
        if (! Customer::query()->whereKey($data->customer_id)->exists()) {
            throw ValidationException::withMessages([
                'customer_id' => 'A valid customer is required.',
            ]);
        }

        if ($data->posted_sales_invoice_id) {
            $postedInvoice = PostedSalesInvoice::query()->find($data->posted_sales_invoice_id);

            if (! $postedInvoice) {
                throw ValidationException::withMessages([
                    'posted_sales_invoice_id' => 'A valid posted invoice is required.',
                ]);
            }

            if ((int) $postedInvoice->customer_id !== $data->customer_id) {
                throw ValidationException::withMessages([
                    'posted_sales_invoice_id' => 'The selected posted invoice does not belong to the selected customer.',
                ]);
            }

            if ($postedInvoice->cancelled) {
                throw ValidationException::withMessages([
                    'posted_sales_invoice_id' => 'Cancelled posted invoices cannot be credited.',
                ]);
            }
        }

        if ($data->items->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Sales credit memo must have at least one credit line.',
            ]);
        }

        /** @var SalesCreditMemoLineData $line */
        foreach ($data->items as $line) {
            if ($line->quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Credit memo line quantity must be greater than zero.',
                ]);
            }

            if (! Item::query()->whereKey($line->item_id)->exists()) {
                throw ValidationException::withMessages([
                    'items' => 'Each credit memo line must reference a valid item.',
                ]);
            }

            if ($data->posted_sales_invoice_id) {
                $postedInvoiceLine = $this->postedInvoiceLineForData($data, $line);

                if (! $postedInvoiceLine) {
                    throw ValidationException::withMessages([
                        'items' => 'Each invoice-linked credit memo line must reference a valid posted invoice line.',
                    ]);
                }

                if ((int) $postedInvoiceLine->item_id !== $line->item_id) {
                    throw ValidationException::withMessages([
                        'items' => 'Credit memo line item must match the selected posted invoice line.',
                    ]);
                }

                $availableQuantity = $this->remainingReturnableQuantityForPostedInvoiceLine($postedInvoiceLine);

                if ($line->quantity > ($availableQuantity + 0.000001)) {
                    throw ValidationException::withMessages([
                        'items' => "Credit memo line quantity exceeds remaining returnable quantity. Available: {$availableQuantity}, requested: {$line->quantity}.",
                    ]);
                }
            }
        }
    }

    private function postedInvoiceLineForData(SalesCreditMemoData $data, SalesCreditMemoLineData $line): ?PostedSalesInvoiceLine
    {
        if (! $data->posted_sales_invoice_id || ! $line->posted_sales_invoice_line_id) {
            return null;
        }

        return PostedSalesInvoiceLine::query()
            ->whereKey($line->posted_sales_invoice_line_id)
            ->where('posted_sales_invoice_id', $data->posted_sales_invoice_id)
            ->first();
    }

    private function remainingReturnableQuantityForPostedInvoiceLine(PostedSalesInvoiceLine $line): float
    {
        $alreadyCredited = PostedSalesCreditMemoLine::query()
            ->join('posted_sales_credit_memos as headers', 'headers.id', '=', 'posted_sales_credit_memo_lines.posted_sales_credit_memo_id')
            ->where('posted_sales_credit_memo_lines.corrected_invoice_line_id', $line->id)
            ->where('headers.corrected', false)
            ->sum('posted_sales_credit_memo_lines.quantity');

        return max(0.0, abs((float) $line->quantity) - abs((float) $alreadyCredited));
    }

    private function generateMemoNumber(): string
    {
        try {
            return SalesCreditMemo::generateMemoNumber();
        } catch (NumberSeriesException $exception) {
            throw new NumberSeriesException(
                'Sales Credit Memo number series is not configured. Configure one of: S-CM, SALES_CREDIT_MEMO, SCM.',
                ['S-CM', 'SALES_CREDIT_MEMO', 'SCM'],
                title: 'Sales Credit Memo Number Series is not configured',
                codeIdentifier: 'sales_credit_memo_number_series_missing',
                previous: $exception,
            );
        }
    }
}
