<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesCreditMemoData;
use App\Models\Item;
use App\Models\SalesCreditMemo;
use App\Services\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesCreditMemoService
{
    /**
     * @throws \Throwable
     */
    public function create(SalesCreditMemoData $data): SalesCreditMemo
    {
        return DB::transaction(function () use ($data) {

            $this->validateData($data);

            $creditMemo = SalesCreditMemo::create([
                'customer_id'   => $data->customer_id,
                'memo_number'   => $data->memo_number ?? $this->generateNumber(),
                'status'        => 'draft',
                'reason'        => $data->reason,
                'effective_date'=> $data->effective_date,
            ]);

            $total = 0;

            foreach ($data->items as $line) {

                $item = Item::findOrFail($line['item_id']);

                $lineTotal = $line['quantity'] * $line['price'];

                $creditMemo->items()->create([
                    'item_id'  => $item->id, // FIXED naming
                    'quantity' => $line['quantity'],
                    'price'    => $line['price'],
                    'total'    => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $creditMemo->update([
                'total_amount' => $total,
            ]);

            return $creditMemo;
        });
    }

    /**
     * @throws \Throwable
     */
    public function update(SalesCreditMemo $creditMemo, SalesCreditMemoData $data): SalesCreditMemo
    {
        if ($creditMemo->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Posted credit memo cannot be modified.',
            ]);
        }

        if ($creditMemo->status !== 'approved') {
            throw new \Exception('Credit Memo must be approved before posting.');
        }

        return DB::transaction(function () use ($creditMemo, $data) {

            $creditMemo->update([
                'reason'         => $data->reason,
                'effective_date' => $data->effective_date,
            ]);

            // 🔥 Replace lines (BC behavior)
            $creditMemo->items()->delete();

            $total = 0;

            foreach ($data->items as $line) {

                $lineTotal = $line['quantity'] * $line['price'];

                $creditMemo->items()->create([
                    'item_id'  => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'price'    => $line['price'],
                    'total'    => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $creditMemo->update([
                'total_amount' => $total,
            ]);

            return $creditMemo;
        });
    }

    public function requestApproval(SalesCreditMemo $creditMemo): SalesCreditMemo
    {
        if ($creditMemo->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft credit memos can be sent for approval.',
            ]);
        }

        if ($creditMemo->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'Cannot request approval without lines.',
            ]);
        }

        $creditMemo->update([
            'status' => 'pending',
        ]);

        return $creditMemo;
    }

    public function post(SalesCreditMemo $creditMemo): void
    {
        if ($creditMemo->status === 'posted') {
            throw ValidationException::withMessages([
                'status' => 'Already posted.',
            ]);
        }

        DB::transaction(function () use ($creditMemo) {

            $creditMemo->load('items');

            if ($creditMemo->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Cannot post without lines.',
                ]);
            }

            // 🔥 1. Reverse Inventory (if applicable)
            foreach ($creditMemo->items as $line) {

                $item = Item::find($line->item_id);

                if ($item && $item->isInventoryItem()) {
                    $item->increment('inventory', $line->quantity);
                }
            }

            // 🔥 2. Financial Posting (DOUBLE ENTRY)
            app(PostingService::class)
                ->postSalesCreditMemo($creditMemo);

            // 🔥 3. Mark as posted
            $creditMemo->update([
                'status' => 'posted',
                'posted_at' => now(),
            ]);
        });
    }

    private function validateData(SalesCreditMemoData $data): void
    {
        if (empty($data->items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one line is required.',
            ]);
        }
    }

    private function generateNumber(): string
    {
        return 'SCM-' . now()->format('YmdHis');
    }
}
