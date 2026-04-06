<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Models\SalesInvoice;
use App\Services\PostingService;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    /**
     * @throws \Throwable
     */
    public function create(SalesInvoiceData $data): SalesInvoice
    {
        return DB::transaction(function () use ($data) {

            // ✅ Determine initial status
            $status = auth()->user()->isSuperAdmin()
                ? ApprovalStatus::APPROVED
                : ApprovalStatus::PENDING;

            $invoice = SalesInvoice::create([
                'customer_id' => $data->customer_id,
                'invoice_number' => $data->invoice_number ?? $this->generateNumber(),
                'status' => $status,
                'invoice_date' => $data->invoice_date,
                'due_date' => $data->due_date,
                'approved_by' => auth()->user()->isSuperAdmin() ? auth()->id() : null,
                'approved_at' => auth()->user()->isSuperAdmin() ? now() : null,
            ]);

            $total = 0;

            foreach ($data->items as $line) {
                $lineTotal = $line['quantity'] * $line['unit_price'];

                $invoice->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $invoice->update(['total_amount' => $total]);

            $total += $lineTotal;

            return $invoice;
        });
    }

    /**
     * @throws \Throwable
     */
    public function post(SalesInvoice $invoice): void
    {
        if ($invoice->isPosted()) {
            throw new \Exception("Already posted");
        }

        DB::transaction(function () use ($invoice) {

            $invoice->load('lines');

            if ($invoice->lines->isEmpty()) {
                throw new \Exception("No lines to post");
            }

            // 🔥 1. Inventory reduction
            foreach ($invoice->lines as $line) {

                if ($line->item && $line->item->isInventoryItem()) {
                    $line->item->decrement('inventory', $line->quantity);
                }
            }

            // 🔥 2. Financial posting
            app(PostingService::class)
                ->postSalesInvoice($invoice);

            // 🔥 3. Mark as posted
            $invoice->update([
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => auth()->id(),
            ]);
        });
    }

    private function generateNumber(): string
    {
        return 'INV-' . now()->format('YmdHis');
    }
}
