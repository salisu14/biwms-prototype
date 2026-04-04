<?php

namespace App\Services\Sales;

use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesCreditMemoLine;
use Illuminate\Support\Facades\DB;
use Exception;
use DateTime;

class PostedSalesInvoiceService
{
    /**
     * Record a payment
     */
    public function applyPayment(PostedSalesInvoice $invoice, float $amount, DateTime $paymentDate): void
    {
        $invoice->amount_paid += $amount;
        $invoice->remaining_amount = $invoice->grand_total - $invoice->amount_paid;

        if ($invoice->remaining_amount <= 0.01) {
            $invoice->paid_in_full = true;
            $invoice->paid_in_full_date = $paymentDate;
            $invoice->remaining_amount = 0;
        }

        $invoice->save();
    }

    /**
     * Cancel invoice (creates credit memo)
     */
    public function cancel(PostedSalesInvoice $invoice, int $userId, string $reason): PostedSalesCreditMemo
    {
        if ($invoice->cancelled) {
            throw new Exception('Invoice is already cancelled');
        }

        return DB::transaction(function () use ($invoice, $userId, $reason) {
            // Create credit memo
            $creditMemo = PostedSalesCreditMemo::create([
                'document_number' => PostedSalesCreditMemo::generateNumber(),
                'corrected_invoice_id' => $invoice->id,
                'corrected_invoice_number' => $invoice->document_number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer_name,
                'posting_date' => now(),
                'total_amount' => -$invoice->total_amount,
                'total_vat' => -$invoice->total_vat,
                'grand_total' => -$invoice->grand_total,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            // Reverse G/L entries / lines
            foreach ($invoice->lines as $line) {
                PostedSalesCreditMemoLine::create([
                    'posted_sales_credit_memo_id' => $creditMemo->id,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->item_description,
                    'quantity' => -$line->quantity,
                    'unit_price' => $line->unit_price,
                    'line_amount' => -$line->line_amount,
                    'vat_amount' => -$line->vat_amount,
                    'amount_including_vat' => -$line->amount_including_vat,
                ]);
            }

            // Mark invoice cancelled
            $invoice->update([
                'cancelled' => true,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'corrective_document_number' => $creditMemo->document_number,
            ]);

            return $creditMemo;
        });
    }

    /**
     * Generate document number
     */
    public function generateNumber(): string
    {
        $prefix = 'SI';
        $year = date('Y');
        $count = PostedSalesInvoice::whereYear('posted_at', $year)->count() + 1;
        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
