<?php

namespace App\Services\Purchase;

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\NumberSeries;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\NumberSeriesService;
use App\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceService
{
    public function __construct(private readonly NumberSeriesService $numberSeriesService) {}

    public function createFromOrder(PurchaseOrder $order): PurchaseInvoice
    {
        return DB::transaction(function () use ($order): PurchaseInvoice {
            $order->loadMissing(['vendor', 'lines.item']);

            $linesToInvoice = $order->lines
                ->map(function ($line): array {
                    $quantityToInvoice = max(0, (float) $line->received_quantity - (float) $line->invoiced_quantity);

                    return [
                        'line' => $line,
                        'quantity' => $quantityToInvoice,
                    ];
                })
                ->filter(fn (array $row): bool => (float) $row['quantity'] > 0)
                ->values();

            // Legacy-safe fallback: if status says received but line received quantities were never updated,
            // invoice remaining ordered qty minus already invoiced qty.
            if ($linesToInvoice->isEmpty() && $order->status === PurchaseOrderStatus::RECEIVED) {
                $linesToInvoice = $order->lines
                    ->map(function ($line): array {
                        $quantityToInvoice = max(0, (float) $line->quantity - (float) $line->invoiced_quantity);

                        return [
                            'line' => $line,
                            'quantity' => $quantityToInvoice,
                        ];
                    })
                    ->filter(fn (array $row): bool => (float) $row['quantity'] > 0)
                    ->values();
            }

            if ($linesToInvoice->isEmpty()) {
                throw new \RuntimeException('Nothing to invoice. All received quantities are already invoiced.');
            }

            $invoice = PurchaseInvoice::create([
                'document_number' => $this->generateNumber(),
                'external_document_number' => null,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'vendor_id' => $order->vendor_id,
                'vendor_name' => $order->vendor_name ?? $order->vendor?->vendor_name,
                'vendor_address' => $order->vendor?->address,
                'general_business_posting_group_id' => $order->general_business_posting_group_id,
                'vendor_posting_group_id' => $order->vendor_posting_group_id,
                'vat_business_posting_group_id' => $order->vat_business_posting_group_id,
                'location_id' => $order->location_id,
                'posting_date' => now()->toDateString(),
                'document_date' => now()->toDateString(),
                'due_date' => now()->addDays((int) ($order->payment_terms ?: 30))->toDateString(),
                'currency_code' => $order->currency_code ?: 'USD',
                'currency_factor' => 1,
                'amount_paid' => 0,
                'remaining_amount' => 0,
                'paid_in_full' => false,
                'status' => ApprovalStatus::APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'cancelled' => false,
            ]);

            $totalAmount = 0.0;
            $totalVat = 0.0;
            $lineNo = 0;

            foreach ($linesToInvoice as $row) {
                $lineNo += 10;
                $line = $row['line'];
                $quantity = (float) $row['quantity'];
                $lineTotal = $quantity * (float) $line->unit_cost;
                $vatAmount = $lineTotal * ((float) $line->vat_percentage / 100);

                $invoice->lines()->create([
                    'line_number' => $lineNo,
                    'po_line_id' => $line->id,
                    'po_line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->description,
                    'variant_code' => $line->variant_code,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->item?->inventory_posting_group_id,
                    'quantity' => $quantity,
                    'unit_of_measure_code' => $line->unit_of_measure,
                    'qty_per_unit_of_measure' => 1,
                    'quantity_base' => $quantity,
                    'unit_cost' => $line->unit_cost,
                    'unit_cost_lcy' => $line->unit_cost,
                    'line_total' => $lineTotal,
                    'line_discount_amount' => 0,
                    'line_discount_percent' => 0,
                    'vat_code' => $line->vat_code,
                    'vat_percentage' => $line->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'vat_amount_lcy' => $vatAmount,
                    'amount_including_vat' => $lineTotal + $vatAmount,
                    'amount_including_vat_lcy' => $lineTotal + $vatAmount,
                    'posting_date' => $invoice->posting_date,
                ]);

                $line->increment('invoiced_quantity', $quantity);
                $totalAmount += $lineTotal;
                $totalVat += $vatAmount;
            }

            $invoice->update([
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
                'remaining_amount' => $totalAmount + $totalVat,
            ]);

            $order->refresh();

            return $invoice->fresh('lines');
        });
    }

    public function post(PurchaseInvoice $invoice): PostedPurchaseInvoice
    {
        if ($invoice->isPosted()) {
            throw new \RuntimeException('Purchase invoice is already posted.');
        }

        if ($invoice->status !== ApprovalStatus::APPROVED) {
            throw new \RuntimeException('Only approved purchase invoices can be posted.');
        }

        return DB::transaction(function () use ($invoice): PostedPurchaseInvoice {
            $invoice->loadMissing(['lines.item', 'vendor', 'purchaseOrder']);

            if ($invoice->lines->isEmpty()) {
                throw new \RuntimeException('No lines to post for this purchase invoice.');
            }

            foreach ($invoice->lines as $line) {
                app(PostingService::class)->postPurchaseLine(
                    vendor: $invoice->vendor,
                    item: $line->item,
                    quantity: (float) $line->quantity,
                    unitCost: (float) $line->unit_cost,
                    lineTotal: (float) $line->line_total,
                    postingDate: $invoice->posting_date,
                    documentNumber: $invoice->document_number,
                    description: $line->item_description ?? $line->item?->description ?? 'Purchase Invoice Line',
                    vatAmount: (float) $line->vat_amount
                );
            }

            app(PostingService::class)->postVendorPayable(
                vendor: $invoice->vendor,
                amount: (float) $invoice->grand_total,
                postingDate: $invoice->posting_date,
                documentNumber: $invoice->document_number
            );

            $posted = PostedPurchaseInvoice::query()->firstOrCreate(
                ['document_number' => $invoice->document_number],
                [
                    'external_document_number' => $invoice->external_document_number,
                    'order_id' => $invoice->order_id,
                    'order_number' => $invoice->order_number,
                    'vendor_id' => $invoice->vendor_id,
                    'vendor_name' => $invoice->vendor_name,
                    'vendor_address' => $invoice->vendor_address,
                    'general_business_posting_group_id' => $invoice->general_business_posting_group_id,
                    'vendor_posting_group_id' => $invoice->vendor_posting_group_id,
                    'vat_business_posting_group_id' => $invoice->vat_business_posting_group_id,
                    'location_id' => $invoice->location_id,
                    'posting_date' => $invoice->posting_date,
                    'document_date' => $invoice->document_date,
                    'due_date' => $invoice->due_date,
                    'vat_date' => $invoice->vat_date,
                    'total_amount' => $invoice->total_amount,
                    'total_vat' => $invoice->total_vat,
                    'grand_total' => $invoice->grand_total,
                    'currency_code' => $invoice->currency_code,
                    'currency_factor' => $invoice->currency_factor,
                    'amount_paid' => $invoice->amount_paid,
                    'remaining_amount' => $invoice->remaining_amount,
                    'paid_in_full' => $invoice->paid_in_full,
                    'paid_in_full_date' => $invoice->paid_in_full_date,
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                    'cancelled' => false,
                    'dimensions' => $invoice->dimensions,
                ]
            );

            $posted->lines()->delete();

            foreach ($invoice->lines as $line) {
                PostedPurchaseInvoiceLine::query()->create([
                    'posted_purchase_invoice_id' => $posted->id,
                    'po_line_id' => $line->po_line_id,
                    'po_line_number' => $line->po_line_number,
                    'item_id' => $line->item_id,
                    'item_code' => $line->item_code,
                    'item_description' => $line->item_description,
                    'variant_code' => $line->variant_code,
                    'general_product_posting_group_id' => $line->general_product_posting_group_id,
                    'inventory_posting_group_id' => $line->inventory_posting_group_id,
                    'gl_account_id' => $line->gl_account_id,
                    'gl_account_number' => $line->gl_account_number,
                    'gl_account_name' => $line->gl_account_name,
                    'quantity' => $line->quantity,
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $line->qty_per_unit_of_measure,
                    'quantity_base' => $line->quantity_base,
                    'unit_cost' => $line->unit_cost,
                    'unit_cost_lcy' => $line->unit_cost_lcy,
                    'line_total' => $line->line_total,
                    'line_discount_amount' => $line->line_discount_amount,
                    'line_discount_percent' => $line->line_discount_percent,
                    'vat_code' => $line->vat_code,
                    'vat_percentage' => $line->vat_percentage,
                    'vat_amount' => $line->vat_amount,
                    'vat_amount_lcy' => $line->vat_amount_lcy,
                    'amount_including_vat' => $line->amount_including_vat,
                    'amount_including_vat_lcy' => $line->amount_including_vat_lcy,
                    'lot_number' => $line->lot_number,
                    'serial_number' => $line->serial_number,
                    'expiration_date' => $line->expiration_date,
                    'dimensions' => $line->dimensions,
                    'item_ledger_entry_id' => $line->item_ledger_entry_id,
                    'gl_entry_id' => $line->gl_entry_id,
                    'line_number' => $line->line_number,
                    'posting_date' => $invoice->posting_date,
                ]);
            }

            $invoice->update([
                'status' => ApprovalStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            if ($invoice->purchaseOrder) {
                $invoice->purchaseOrder->refreshLifecycleStatus();
            }

            return $posted;
        });
    }

    private function generateNumber(): string
    {
        $seriesCandidates = ['P-INV', 'PURCHASE_INVOICE', 'PI'];

        foreach ($seriesCandidates as $seriesCode) {
            $nextNo = $this->numberSeriesService->tryGetNextNo($seriesCode);
            if ($nextNo !== null && $nextNo !== '') {
                return $nextNo;
            }
        }

        $legacySeries = NumberSeries::query()
            ->whereIn('code', $seriesCandidates)
            ->where('is_active', true)
            ->orderByRaw(
                "CASE code
                    WHEN 'P-INV' THEN 1
                    WHEN 'PURCHASE_INVOICE' THEN 2
                    WHEN 'PI' THEN 3
                    ELSE 99
                END"
            )
            ->first();

        if ($legacySeries) {
            $fallbackNo = $legacySeries->generateNumber();
            if (! empty($fallbackNo)) {
                return $fallbackNo;
            }
        }

        return PurchaseInvoice::generateNumber();
    }
}
