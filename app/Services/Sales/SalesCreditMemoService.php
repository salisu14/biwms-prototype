<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesCreditMemoData;
use App\Enums\ApprovalStatus;
use App\Models\Item;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesCreditMemoLine;
use App\Models\SalesCreditMemo;
use App\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        if ($creditMemo->status !== ApprovalStatus::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved credit memos can be posted.',
            ]);
        }

        DB::transaction(function () use ($creditMemo) {
            // 1. Create Posted Document
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
                'total_amount' => $creditMemo->total_amount,
                'grand_total' => $creditMemo->total_amount,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'corrected_invoice_id' => $creditMemo->sales_invoice_id,
                'corrected_invoice_number' => $creditMemo->invoice?->invoice_number,
                'return_reason_comment' => $creditMemo->reason,
            ]);

            // 2. Stock Adjustment and Lines
            foreach ($creditMemo->items as $line) {
                $item = $line->item;

                PostedSalesCreditMemoLine::create([
                    'posted_sales_credit_memo_id' => $postedMemo->id,
                    'line_number' => $line->id * 10, // simple sequence
                    'item_id' => $line->item_id,
                    'item_code' => $item->item_number,
                    'item_description' => $line->description ?? $item->description,
                    'general_product_posting_group_id' => $item->general_product_posting_group_id,
                    'inventory_posting_group_id' => $item->inventory_posting_group_id,
                    'quantity' => -$line->quantity, // Posted lines usually use Negative for returns in this app
                    'unit_price' => $line->unit_price,
                    'line_amount' => $line->total,
                    'amount_including_vat' => $line->total, // assuming inclusive or no vat for this simple mapping
                ]);

                if ($item->isInventoryItem()) {
                    $item->increment('inventory', $line->quantity);
                }
            }

            // 3. Financial Posting
            $this->postingService->postSalesCreditMemo($creditMemo);

            // 4. Mark draft as posted
            $creditMemo->update([
                'status' => ApprovalStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);
        });
    }
}
