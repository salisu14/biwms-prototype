<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesCreditMemoData;
use App\Enums\ApprovalStatus;
use App\Enums\ItemLedgerEntryType;
use App\Models\CustomerLedgerEntry;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesCreditMemoLine;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\PostingService;
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
        return DB::transaction(function () use ($data) {
            $creditMemo = SalesCreditMemo::create([
                'customer_id' => $data->customer_id,
                'sales_invoice_id' => $data->sales_invoice_id,
                'memo_number' => $data->memo_number,
                'status' => ApprovalStatus::DRAFT,
                'reason' => $data->reason,
                'effective_date' => $data->effective_date ?? now(),
                'currency_code' => $data->currency_code,
            ]);

            foreach ($data->items as $line) {
                $item = Item::findOrFail($line->item_id);

                $creditMemo->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'vat_percent' => $line->vat_percent,
                    'description' => $line->description ?? $item->description,
                ]);
            }

            $creditMemo->refreshTotal();

            return $creditMemo;
        });
    }

    /**
     * @throws \Throwable
     */
    public function update(SalesCreditMemo $creditMemo, SalesCreditMemoData $data): SalesCreditMemo
    {
        if ($creditMemo->status !== ApprovalStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft credit memos can be modified.',
            ]);
        }

        return DB::transaction(function () use ($creditMemo, $data) {
            $creditMemo->update([
                'customer_id' => $data->customer_id,
                'sales_invoice_id' => $data->sales_invoice_id,
                'reason' => $data->reason,
                'effective_date' => $data->effective_date ?? now(),
                'currency_code' => $data->currency_code,
            ]);

            $creditMemo->items()->delete();

            foreach ($data->items as $line) {
                $item = Item::findOrFail($line->item_id);

                $creditMemo->items()->create([
                    'item_id' => $item->id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'vat_percent' => $line->vat_percent,
                    'description' => $line->description ?? $item->description,
                ]);
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
            throw new \Exception('Unauthenticated user');
        }

        Gate::forUser(User::query()->findOrFail($userId))->authorize('post', $creditMemo);

        if ($creditMemo->isPosted()) {
            throw new \Exception('Sales credit memo is already posted.');
        }

        if ($creditMemo->status !== ApprovalStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved credit memos can be posted.',
            ]);
        }

        DB::transaction(function () use ($creditMemo) {
            $creditMemo->loadMissing(['items.item', 'customer', 'invoice']);

            if ($creditMemo->items->isEmpty()) {
                throw new \Exception('No lines to post for this sales credit memo.');
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
                'corrected_invoice_id' => $creditMemo->sales_invoice_id,
                'corrected_invoice_number' => $creditMemo->invoice?->invoice_number,
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

    private function createItemLedgerEntryForLine(PostedSalesCreditMemo $postedMemo, SalesCreditMemoLine $line): ?ItemLedgerEntry
    {
        $item = $line->item;

        if (! $item || ! $item->isInventoryItem()) {
            return null;
        }

        $quantityBase = $this->quantityBase($line, $item);

        if ($quantityBase <= 0) {
            throw new \Exception("Quantity must be greater than zero for item {$item->item_code}");
        }

        $locationId = $postedMemo->location_id ?? $item->location_id ?? $postedMemo->customer?->location_id;

        if (! $locationId) {
            throw new \Exception("Location is missing for item {$item->item_code} on sales credit memo {$postedMemo->document_number}.");
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

        $this->assertValueEntryCreated($entry, $postedMemo->corrected_invoice_number, $postedMemo->posting_date);

        $item->increment('inventory', $quantityBase);

        return $entry;
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
}
