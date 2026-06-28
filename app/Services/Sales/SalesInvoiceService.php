<?php

namespace App\Services\Sales;

use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Enums\ItemLedgerEntryType;
use App\Models\CustomerLedgerEntry;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\ValueEntry;
use App\Services\AuditTrailService;
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

            $invoice = SalesInvoice::create([
                'customer_id' => $data->customer_id,
                'sales_order_id' => $data->sales_order_id,
                'invoice_number' => $data->invoice_number ?? $this->generateNumber(),
                'status' => ApprovalStatus::DRAFT,
                'invoice_date' => $data->invoice_date,
                'due_date' => $data->due_date,
                'currency_code' => $data->currency_code ?? 'NGN',
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
        DB::transaction(function () use ($invoice) {
            $invoice = SalesInvoice::query()
                ->with(['lines.item', 'customer', 'salesOrder'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->isPosted()) {
                throw new \Exception('Invoice already posted');
            }

            if ($invoice->status !== ApprovalStatus::APPROVED) {
                throw new \Exception('Only approved invoices can be posted');
            }

            if ($invoice->lines->isEmpty()) {
                throw new \Exception('No lines to post');
            }

            $itemLedgerEntryIds = [];

            foreach ($invoice->lines as $line) {
                $itemLedgerEntry = $this->createItemLedgerEntryForLine($invoice, $line);

                if ($itemLedgerEntry) {
                    $itemLedgerEntryIds[$line->id] = $itemLedgerEntry->id;
                }
            }

            app(PostingService::class)->postSalesInvoice($invoice);

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
                $conversionFactor = $item ? $this->conversionFactor($item, $line->unit_of_measure) : 1.0;
                $quantityBase = $item ? $this->quantityBase($line, $item) : $quantity;
                $unitPrice = (float) $line->unit_price;
                $lineSubTotal = $quantity * $unitPrice;
                $discountAmount = (float) ($line->discount_amount ?? 0);
                $lineAmount = max(0, $lineSubTotal - $discountAmount);
                $vatAmount = (float) ($line->vat_amount ?? 0);
                $amountIncludingVat = $lineAmount + $vatAmount;
                $unitCost = (float) ($item?->unit_cost ?? 0);
                $costAmount = $quantityBase * $unitCost;

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
                    'qty_per_unit_of_measure' => $conversionFactor,
                    'quantity_base' => $quantityBase,
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
                    'item_ledger_entry_id' => $itemLedgerEntryIds[$line->id] ?? null,
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

            $invoiceLedgerExists = CustomerLedgerEntry::query()
                ->where('document_type', 'SALES_INVOICE')
                ->where('document_number', $postedInvoice->document_number)
                ->where('customer_id', $postedInvoice->customer_id)
                ->exists();

            if (! $invoiceLedgerExists) {
                CustomerLedgerEntry::createFromInvoice($postedInvoice);
            }

            $invoice->update([
                'status' => ApprovalStatus::POSTED,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
            ]);

            app(AuditTrailService::class)->recordGeneric(
                eventType: 'posting',
                action: 'sales_invoice_posted',
                auditable: $invoice,
                documentType: 'SALES_INVOICE',
                documentNo: $invoice->invoice_number,
                userId: Auth::id(),
                description: "Sales invoice {$invoice->invoice_number} posted",
                metadata: [
                    'posted_sales_invoice_id' => $postedInvoice->id,
                    'total_amount' => $grandTotal,
                ],
            );
        });
    }

    private function createItemLedgerEntryForLine(SalesInvoice $invoice, SalesInvoiceLine $line): ?ItemLedgerEntry
    {
        $item = $line->item;

        if (! $item || ! $item->isInventoryItem()) {
            return null;
        }

        $quantityBase = $this->quantityBase($line, $item);

        if ($quantityBase <= 0) {
            throw new \Exception("Quantity must be greater than zero for item {$item->item_code}");
        }

        if ((float) $item->ledger_on_hand < $quantityBase) {
            throw new \Exception("Insufficient stock for item: {$item->description}");
        }

        $costAmount = $quantityBase * (float) ($item->unit_cost ?? 0);
        $locationId = $line->location_id ?? $item->location_id ?? $invoice->customer?->location_id;

        if (! $locationId) {
            throw new \Exception("Location is missing for item {$item->item_code} on sales invoice {$invoice->invoice_number}.");
        }

        $entry = ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::SALE,
            'document_type' => 'SALES_INVOICE',
            'document_line_number' => $line->id,
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => -$quantityBase,
            'remaining_quantity' => 0,
            'open' => false,
            'posting_date' => $invoice->invoice_date ?? now()->toDateString(),
            'entry_date' => now(),
            'document_number' => $invoice->invoice_number,
            'source_id' => $invoice->id,
            'source_type' => SalesInvoice::class,
            'cost_amount_actual' => $costAmount,
            'cost_amount_expected' => 0,
            'general_business_posting_group_id' => $invoice->customer?->general_business_posting_group_id,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
        ]);

        $this->assertValueEntryCreated($entry);

        $item->decrement('inventory', $quantityBase);

        return $entry;
    }

    private function quantityBase(SalesInvoiceLine $line, Item $item): float
    {
        return (float) $line->quantity * $this->conversionFactor($item, $line->unit_of_measure);
    }

    private function conversionFactor(Item $item, ?string $unitOfMeasureCode): float
    {
        $conversionFactor = $item->getConversionFactorForUom($unitOfMeasureCode ?: $item->base_unit_of_measure);

        return $conversionFactor > 0 ? $conversionFactor : 1.0;
    }

    private function assertValueEntryCreated(ItemLedgerEntry $entry): void
    {
        $exists = ValueEntry::query()
            ->where('item_ledger_entry_no', $entry->entry_number)
            ->where('document_no', $entry->document_number)
            ->where('document_line_no', $entry->document_line_number)
            ->exists();

        if (! $exists) {
            throw new \RuntimeException("Value Entry was not created for item ledger entry {$entry->entry_number}.");
        }
    }

    private function generateNumber(): string
    {
        return $this->numberSeriesService->getNextNoFromSeries(
            ['S-INV', 'SALES_INVOICE', 'SI'],
            null,
            'Sales Invoice'
        );
    }
}
