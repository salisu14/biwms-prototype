<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Services\NumberSeriesService;
use App\Services\PostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesInvoiceService
{
    public function __construct(
        private readonly NumberSeriesService $numberSeriesService
    ) {}

    /**
     * @throws \Throwable
     */
    public function create(SalesInvoiceData $data): SalesInvoice
    {
        return DB::transaction(function () use ($data) {

            $user = Auth::user();

            if (! $user) {
                throw new \Exception('Unauthenticated user');
            }

            // ✅ Determine initial status (safe)
            $isSuperAdmin = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();

            $status = $isSuperAdmin
                ? ApprovalStatus::APPROVED
                : ApprovalStatus::PENDING;

            $invoice = SalesInvoice::create([
                'customer_id' => $data->customer_id,
                'sales_order_id' => $data->sales_order_id,
                'invoice_number' => $data->invoice_number ?? $this->generateNumber(),
                'status' => $status,
                'invoice_date' => $data->invoice_date,
                'due_date' => $data->due_date,
                'currency_code' => $data->currency_code ?? 'NGN',
                'approved_by' => $isSuperAdmin ? $user->id : null,
                'approved_at' => $isSuperAdmin ? now() : null,
            ]);

            $lines = $data->lines;

            if ($data->sales_order_id && empty($lines)) {
                $salesOrder = SalesOrder::query()
                    ->with('lines')
                    ->find($data->sales_order_id);

                if ($salesOrder) {
                    $lines = $salesOrder->lines
                        ->map(function ($line): array {
                            $quantityToInvoice = max(
                                0,
                                ((float) $line->quantity_shipped > 0
                                    ? (float) $line->quantity_shipped
                                    : (float) $line->quantity) - (float) $line->quantity_invoiced
                            );

                            return [
                                'item_id' => $line->item_id,
                                'description' => $line->description,
                                'quantity' => $quantityToInvoice,
                                'unit_of_measure' => $line->unit_of_measure_code,
                                'unit_price' => (float) $line->unit_price,
                                'discount_percent' => (float) $line->line_discount_percent,
                                'discount_amount' => (float) $line->line_discount_amount,
                                'vat_percent' => (float) $line->vat_percentage,
                            ];
                        })
                        ->filter(fn (array $line): bool => (float) $line['quantity'] > 0)
                        ->values()
                        ->all();
                }
            }

            if (empty($lines)) {
                throw new \Exception('Invoice must have at least one line');
            }

            $total = 0;

            foreach ($lines as $line) {

                if ($line['quantity'] <= 0) {
                    throw new \Exception('Quantity must be greater than zero');
                }

                $lineSubTotal = (float) $line['quantity'] * (float) $line['unit_price'];
                $discountAmount = (float) ($line['discount_amount'] ?? 0);
                if ($discountAmount <= 0 && (float) ($line['discount_percent'] ?? 0) > 0) {
                    $discountAmount = $lineSubTotal * ((float) $line['discount_percent'] / 100);
                }
                $afterDiscount = max(0, $lineSubTotal - $discountAmount);
                $vatAmount = $afterDiscount * ((float) ($line['vat_percent'] ?? 0) / 100);
                $lineTotal = $afterDiscount + $vatAmount;

                $invoice->lines()->create([
                    'item_id' => $line['item_id'],
                    'description' => $line['description'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_of_measure' => $line['unit_of_measure'] ?? null,
                    'unit_price' => $line['unit_price'],
                    'discount_percent' => $line['discount_percent'] ?? 0,
                    'discount_amount' => $discountAmount,
                    'vat_percent' => $line['vat_percent'] ?? 0,
                    'vat_amount' => $vatAmount,
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
            throw new \Exception('Invoice already posted');
        }

        // ✅ Only approved invoices can be posted
        if ($invoice->status !== ApprovalStatus::APPROVED) {
            throw new \Exception('Only approved invoices can be posted');
        }

        DB::transaction(function () use ($invoice) {

            $invoice->load(['lines.item', 'customer']);

            if ($invoice->lines->isEmpty()) {
                throw new \Exception('No lines to post');
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

            // 🔥 2.5 Create posted sales invoice document + lines snapshot
            $postedInvoice = PostedSalesInvoice::query()->firstOrCreate(
                ['document_number' => $invoice->invoice_number],
                [
                    'external_document_number' => null,
                    'order_id' => $invoice->sales_order_id,
                    'order_number' => $invoice->salesOrder?->order_number,
                    'customer_id' => $invoice->customer_id,
                    'customer_name' => $invoice->customer?->name ?? 'Unknown Customer',
                    'customer_address' => $invoice->customer?->address ?? null,
                    'ship_to_name' => $invoice->customer?->name ?? null,
                    'ship_to_address' => $invoice->customer?->address ?? null,
                    'general_business_posting_group_id' => $invoice->customer?->general_business_posting_group_id,
                    'customer_posting_group_id' => $invoice->customer?->customer_posting_group_id,
                    'vat_bus_posting_group' => $invoice->customer?->vat_bus_posting_group,
                    'location_id' => null,
                    'shipping_agent_code' => null,
                    'posting_date' => $invoice->invoice_date ?? now()->toDateString(),
                    'document_date' => $invoice->invoice_date ?? now()->toDateString(),
                    'due_date' => $invoice->due_date ?? now()->toDateString(),
                    'shipment_date' => null,
                    'subtotal' => 0,
                    'line_discount_total' => 0,
                    'invoice_discount_amount' => 0,
                    'total_amount' => 0,
                    'total_vat' => 0,
                    'grand_total' => 0,
                    'currency_code' => $invoice->currency_code ?? 'NGN',
                    'currency_factor' => 1,
                    'amount_paid' => 0,
                    'remaining_amount' => 0,
                    'paid_in_full' => false,
                    'posted_by' => Auth::id(),
                    'posted_at' => now(),
                    'salesperson_id' => null,
                    'cancelled' => false,
                    'dimensions' => null,
                ]
            );

            $postedInvoice->lines()->delete();

            $lineNumber = 0;
            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;
            $totalAmount = 0.0;
            $totalVat = 0.0;

            foreach ($invoice->lines as $line) {
                $lineNumber += 10;

                $item = $line->item;
                $quantity = (float) $line->quantity;
                $unitPrice = (float) $line->unit_price;
                $lineSubTotal = $quantity * $unitPrice;
                $discountAmount = (float) ($line->discount_amount ?? 0);
                $lineAmount = max(0, $lineSubTotal - $discountAmount);
                $vatAmount = (float) ($line->vat_amount ?? 0);
                $amountIncludingVat = $lineAmount + $vatAmount;
                $unitCost = (float) ($item?->unit_cost ?? 0);
                $costAmount = $quantity * $unitCost;

                PostedSalesInvoiceLine::query()->create([
                    'posted_sales_invoice_id' => $postedInvoice->id,
                    'so_line_id' => null,
                    'so_line_number' => null,
                    'item_id' => $line->item_id,
                    'item_code' => $item?->item_code,
                    'item_description' => $line->description ?? ($item?->description ?? 'N/A'),
                    'variant_code' => null,
                    'posting_date' => $postedInvoice->posting_date,
                    'general_product_posting_group_id' => $item?->general_product_posting_group_id,
                    'inventory_posting_group_id' => $item?->inventory_posting_group_id,
                    'sales_account_id' => null,
                    'cogs_account_id' => null,
                    'inventory_account_id' => null,
                    'quantity' => $quantity,
                    'unit_of_measure_code' => $line->unit_of_measure ?: ($item?->base_unit_of_measure ?? 'PCS'),
                    'qty_per_unit_of_measure' => 1,
                    'quantity_base' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'unit_cost_lcy' => $unitCost,
                    'line_discount_percent' => (float) ($line->discount_percent ?? 0),
                    'line_discount_amount' => $discountAmount,
                    'line_total' => $lineSubTotal,
                    'line_amount' => $lineAmount,
                    'vat_code' => $item?->vat_product_posting_group_id ? (string) $item->vat_product_posting_group_id : null,
                    'vat_percentage' => (float) ($line->vat_percent ?? 0),
                    'vat_amount' => $vatAmount,
                    'amount_including_vat' => $amountIncludingVat,
                    'cost_amount' => $costAmount,
                    'profit_amount' => $lineAmount - $costAmount,
                    'lot_number' => null,
                    'serial_number' => null,
                    'expiration_date' => null,
                    'item_ledger_entry_id' => null,
                    'shipment_id' => null,
                    'dimensions' => null,
                    'line_number' => $lineNumber,
                ]);

                $subtotal += $lineSubTotal;
                $lineDiscountTotal += $discountAmount;
                $totalAmount += $lineAmount;
                $totalVat += $vatAmount;
            }

            $grandTotal = $totalAmount + $totalVat;
            $postedInvoice->update([
                'subtotal' => $subtotal,
                'line_discount_total' => $lineDiscountTotal,
                'invoice_discount_amount' => 0,
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $grandTotal,
                'remaining_amount' => $grandTotal,
            ]);

            // Ensure customer subledger receives invoice debit entry for AR tracking.
            $invoiceLedgerExists = CustomerLedgerEntry::query()
                ->where('document_type', 'SALES_INVOICE')
                ->where('document_number', $postedInvoice->document_number)
                ->where('customer_id', $postedInvoice->customer_id)
                ->exists();

            if (! $invoiceLedgerExists) {
                CustomerLedgerEntry::createFromInvoice($postedInvoice);
            }

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
        $seriesCandidates = ['S-INV', 'SALES_INVOICE', 'SI'];

        foreach ($seriesCandidates as $seriesCode) {
            $nextNo = $this->numberSeriesService->tryGetNextNo($seriesCode);
            if ($nextNo !== null && $nextNo !== '') {
                return $nextNo;
            }
        }

        throw new \RuntimeException(
            'No Sales Invoice number series is configured. Please set up one of: S-INV, SALES_INVOICE, or SI.'
        );
    }
}
