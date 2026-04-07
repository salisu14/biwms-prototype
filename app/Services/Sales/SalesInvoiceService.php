<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Models\SalesInvoice;
use App\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    /**
     * @throws \Throwable
     */
    public function create(SalesInvoiceData $data): SalesInvoice
    {
        return DB::transaction(function () use ($data) {

            $user = Auth::user();

            if (!$user) {
                throw new \Exception('Unauthenticated user');
            }

            // ✅ Determine initial status (safe)
            $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            $status = $isSuperAdmin
                ? ApprovalStatus::APPROVED
                : ApprovalStatus::PENDING;

            $invoice = SalesInvoice::create([
                'customer_id' => $data->customer_id,
                'invoice_number' => $data->invoice_number ?? $this->generateNumber(),
                'status' => $status,
                'invoice_date' => $data->invoice_date,
                'due_date' => $data->due_date,
                'approved_by' => $isSuperAdmin ? $user->id : null,
                'approved_at' => $isSuperAdmin ? now() : null,
            ]);

            if (empty($data->lines)) {
                throw new \Exception('Invoice must have at least one line');
            }

            $total = 0;

            foreach ($data->lines as $line) {

                if ($line['quantity'] <= 0) {
                    throw new \Exception('Quantity must be greater than zero');
                }

                $lineTotal = $line['quantity'] * $line['unit_price'];

                $invoice->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_total' => $lineTotal,
                ]);

                $total += $lineTotal;
            }

            $invoice->update([
                'total_amount' => $total,
            ]);

            return $invoice;
        });
    }

    /**
     * @throws \Throwable
     */
    public function post(SalesInvoice $invoice): void
    {
        if ($invoice->isPosted()) {
            throw new \Exception("Invoice already posted");
        }

        // ✅ Only approved invoices can be posted
        if ($invoice->status !== ApprovalStatus::APPROVED) {
            throw new \Exception("Only approved invoices can be posted");
        }

        DB::transaction(function () use ($invoice) {

            $invoice->load('lines');

            if ($invoice->lines->isEmpty()) {
                throw new \Exception("No lines to post");
            }

            // 🔥 1. Inventory reduction
            foreach ($invoice->lines as $line) {

                if ($line->item && $line->item->isInventoryItem()) {

                    if ($line->item->inventory < $line->quantity) {
                        throw new \Exception("Insufficient stock for item: {$line->item->name}");
                    }

                    $line->item->decrement('inventory', $line->quantity);
                }
            }

            // 🔥 2. Financial posting (GL entries)
            app(PostingService::class)->postSalesInvoice($invoice);

            // 🔥 3. Mark as posted (ENUM SAFE)
            $invoice->update([
                'status' => ApprovalStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);
        });
    }

    private function generateNumber(): string
    {
        return 'INV-' . now()->format('YmdHis');
    }
}
