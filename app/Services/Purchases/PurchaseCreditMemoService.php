<?php

namespace App\Services\Purchases;

use App\Data\Purchases\PurchaseCreditMemoData;
use App\Enums\ApprovalStatus;
use App\Models\Item;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseCreditMemoLine;
use App\Models\PurchaseCreditMemo;
use App\Models\Vendor;
use App\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseCreditMemoService
{
    public function __construct(
        protected PostingService $postingService
    ) {}

    /**
     * @throws \Throwable
     */
    public function create(PurchaseCreditMemoData $data): PurchaseCreditMemo
    {
        return DB::transaction(function () use ($data) {
            $vendor = Vendor::findOrFail($data->vendor_id);

            $memo = PurchaseCreditMemo::create([
                'vendor_id' => $data->vendor_id,
                'vendor_name' => $vendor->name,
                'corrects_invoice_id' => $data->corrects_invoice_id,
                'external_document_number' => $data->external_document_number,
                'posting_date' => $data->posting_date ?? now(),
                'document_date' => $data->document_date ?? now(),
                'location_id' => $data->location_id,
                'currency_code' => $data->currency_code,
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
            $vendor = Vendor::findOrFail($data->vendor_id);

            $memo->update([
                'vendor_id' => $data->vendor_id,
                'vendor_name' => $vendor->name,
                'corrects_invoice_id' => $data->corrects_invoice_id,
                'external_document_number' => $data->external_document_number,
                'posting_date' => $data->posting_date ?? now(),
                'document_date' => $data->document_date ?? now(),
                'location_id' => $data->location_id,
                'currency_code' => $data->currency_code,
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
        if ($memo->status !== ApprovalStatus::APPROVED) {
            throw ValidationException::withMessages(['status' => 'Only approved credit memos can be posted.']);
        }

        return DB::transaction(function () use ($memo) {
            // 1. Create Posted Document
            $postedMemo = PostedPurchaseCreditMemo::create([
                'document_number' => $memo->document_number,
                'external_document_number' => $memo->external_document_number,
                'vendor_id' => $memo->vendor_id,
                'vendor_name' => $memo->vendor_name,
                'posting_date' => $memo->posting_date,
                'document_date' => $memo->document_date,
                'vendor_posting_group_id' => $memo->vendor->vendor_posting_group_id,
                'general_business_posting_group_id' => $memo->vendor->general_business_posting_group_id,
                'currency_code' => $memo->currency_code,
                'subtotal' => $memo->subtotal,
                'tax_amount' => $memo->tax_amount,
                'grand_total' => $memo->grand_total,
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'corrects_invoice_id' => $memo->corrects_invoice_id,
                'corrects_invoice_number' => $memo->corrects_invoice_number,
                'reason_code' => $memo->reason_code,
                'description' => $memo->description,
            ]);

            // 2. Map Lines
            foreach ($memo->lines as $line) {
                PostedPurchaseCreditMemoLine::create([
                    'credit_memo_id' => $postedMemo->id,
                    'line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unit_cost,
                    'line_total' => $line->line_total,
                    'tax_percent' => $line->tax_percent,
                    'tax_amount' => $line->tax_amount,
                    'grand_total' => $line->grand_total,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                ]);

                // Adjust Inventory
                $item = $line->item;
                if ($item->isInventoryItem()) {
                    $item->decrement('inventory', $line->quantity);
                }
            }

            // 3. Financial Posting
            $this->postingService->postPurchaseCreditMemo($memo);

            // 4. Update Original Status
            $memo->update(['status' => ApprovalStatus::POSTED]);

            return $postedMemo;
        });
    }
}
