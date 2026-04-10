<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesCreditMemoData;
use App\Enums\ApprovalStatus;
use App\Models\Item;
use App\Models\SalesCreditMemo;
use App\Models\User;
use App\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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
                'customer_id'      => $data->customer_id,
                'sales_invoice_id' => $data->sales_invoice_id,
                'memo_number'      => $data->memo_number,
                'status'           => ApprovalStatus::DRAFT,
                'reason'           => $data->reason,
                'effective_date'   => $data->effective_date ?? now(),
                'currency_code'    => $data->currency_code,
            ]);

            foreach ($data->items as $line) {
                $item = Item::findOrFail($line->item_id);
                
                $creditMemo->items()->create([
                    'item_id'     => $item->id,
                    'quantity'    => $line->quantity,
                    'unit_price'  => $line->unit_price,
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
                'customer_id'      => $data->customer_id,
                'sales_invoice_id' => $data->sales_invoice_id,
                'reason'           => $data->reason,
                'effective_date'   => $data->effective_date ?? now(),
                'currency_code'    => $data->currency_code,
            ]);

            $creditMemo->items()->delete();

            foreach ($data->items as $line) {
                $item = Item::findOrFail($line->item_id);
                
                $creditMemo->items()->create([
                    'item_id'     => $item->id,
                    'quantity'    => $line->quantity,
                    'unit_price'  => $line->unit_price,
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
            // 1. Stock Adjustment (Automatic via Item ledger logic elsewhere or manual here)
            foreach ($creditMemo->items as $line) {
                $item = $line->item;
                if ($item->isInventoryItem()) {
                    $item->increment('inventory', $line->quantity);
                    // Note: In a full system, you would create an Item Ledger Entry here
                }
            }

            // 2. Financial Posting
            $this->postingService->postSalesCreditMemo($creditMemo);

            // 3. Mark as posted
            $creditMemo->update([
                'status' => ApprovalStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);
        });
    }
}
