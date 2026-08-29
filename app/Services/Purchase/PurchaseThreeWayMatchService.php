<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceiptLine;
use Illuminate\Support\Collection;

class PurchaseThreeWayMatchService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     title: string,
     *     summary: array<string, mixed>,
     *     rows: Collection<int, object>,
     *     filters: array<string, mixed>
     * }
     */
    public function generate(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        $rows = $this->purchaseOrderLineRows($filters)
            ->concat($this->directInvoiceRows($filters))
            ->sortBy([
                ['reference_number', 'asc'],
                ['item_code', 'asc'],
                ['line_number', 'asc'],
            ])
            ->values();

        $rows = $this->applyFilters($rows, $filters);

        return [
            'title' => 'Purchase Three-Way Match',
            'summary' => $this->summary($rows),
            'rows' => $rows,
            'filters' => $filters,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{business_id:int|null,vendor_id:int|null,purchase_order_id:int|null,date_from:string|null,date_to:string|null,match_status:string|null}
     */
    public function normalizeFilters(array $filters = []): array
    {
        return [
            'business_id' => filled($filters['business_id'] ?? null)
                ? (int) $filters['business_id']
                : (filled(session('active_business_id')) ? (int) session('active_business_id') : null),
            'vendor_id' => filled($filters['vendor_id'] ?? null) ? (int) $filters['vendor_id'] : null,
            'purchase_order_id' => filled($filters['purchase_order_id'] ?? null) ? (int) $filters['purchase_order_id'] : null,
            'date_from' => filled($filters['date_from'] ?? null) ? (string) $filters['date_from'] : null,
            'date_to' => filled($filters['date_to'] ?? null) ? (string) $filters['date_to'] : null,
            'match_status' => filled($filters['match_status'] ?? null) ? (string) $filters['match_status'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function purchaseOrderLineRows(array $filters): Collection
    {
        $query = PurchaseOrderLine::query()
            ->with(['purchaseOrder.vendor', 'item'])
            ->whereHas('purchaseOrder', function ($query) use ($filters): void {
                if ($filters['business_id']) {
                    $query->where('business_id', $filters['business_id']);
                }

                if ($filters['purchase_order_id']) {
                    $query->whereKey($filters['purchase_order_id']);
                }

                if ($filters['vendor_id']) {
                    $query->where('vendor_id', $filters['vendor_id']);
                }

                if ($filters['date_from']) {
                    $query->whereDate('order_date', '>=', $filters['date_from']);
                }

                if ($filters['date_to']) {
                    $query->whereDate('order_date', '<=', $filters['date_to']);
                }
            });

        $poLineIds = $query->pluck('id');

        $receiptRows = PurchaseReceiptLine::query()
            ->with(['purchaseReceipt.receivingLocation'])
            ->whereIn('purchase_order_line_id', $poLineIds)
            ->get()
            ->groupBy('purchase_order_line_id');

        $invoiceRows = PurchaseInvoiceLine::query()
            ->with(['postedPurchaseInvoice'])
            ->whereIn('po_line_id', $poLineIds)
            ->get()
            ->groupBy('po_line_id');

        return $query->get()->map(function (PurchaseOrderLine $line) use ($receiptRows, $invoiceRows): object {
            $receipts = $receiptRows->get($line->id, collect());
            $invoices = $invoiceRows->get($line->id, collect());

            $orderedQuantity = (float) $line->quantity;
            $receivedQuantity = (float) $receipts->sum(fn (PurchaseReceiptLine $receiptLine): float => (float) ($receiptLine->quantity_received ?: $receiptLine->quantity));
            $invoicedQuantity = (float) $invoices->sum('quantity');
            $poUnitCost = (float) $line->unit_cost;
            $invoiceValue = (float) $invoices->sum('line_total');
            $invoicedUnitCost = $invoicedQuantity > 0 ? round($invoiceValue / $invoicedQuantity, 4) : null;
            $receivedValue = (float) $receipts->sum(fn (PurchaseReceiptLine $receiptLine): float => (float) ($receiptLine->line_amount ?? ((float) ($receiptLine->quantity_received ?: $receiptLine->quantity) * (float) $receiptLine->direct_unit_cost)));

            $row = (object) [
                'reference_type' => 'PURCHASE_ORDER',
                'reference_number' => $line->purchaseOrder?->order_number ?? '—',
                'reference_url' => $line->purchaseOrder
                    ? PurchaseOrderResource::getUrl('view', ['record' => $line->purchaseOrder], panel: 'admin')
                    : null,
                'order_id' => $line->purchase_order_id,
                'vendor_id' => $line->purchaseOrder?->vendor_id,
                'vendor_number' => $line->purchaseOrder?->vendor?->vendor_code,
                'vendor_name' => $line->purchaseOrder?->vendor_name ?: $line->purchaseOrder?->vendor?->vendor_name,
                'item_code' => $line->item_code ?: $line->item?->item_code,
                'description' => $line->description ?: $line->item?->description ?: '—',
                'unit_of_measure_code' => $line->unit_of_measure,
                'line_number' => $line->line_number,
                'ordered_quantity' => $orderedQuantity,
                'received_quantity' => $receivedQuantity,
                'invoiced_quantity' => $invoicedQuantity,
                'remaining_to_receive' => max(0, $orderedQuantity - $receivedQuantity),
                'remaining_to_invoice' => max(0, $receivedQuantity - $invoicedQuantity),
                'po_unit_cost' => $poUnitCost,
                'received_value' => $receivedValue,
                'invoice_unit_cost' => $invoicedUnitCost,
                'invoiced_value' => $invoiceValue,
                'quantity_variance' => $receivedQuantity - $invoicedQuantity,
                'price_variance' => $invoicedUnitCost === null ? null : round($invoicedUnitCost - $poUnitCost, 4),
                'amount_variance' => round($invoiceValue - ($orderedQuantity * $poUnitCost), 4),
                'match_status' => $this->classifyLine($orderedQuantity, $receivedQuantity, $invoicedQuantity, $poUnitCost, $invoicedUnitCost),
                'invoice_document_number' => $invoices->pluck('postedPurchaseInvoice.document_number')->filter()->implode(', '),
                'receipt_document_number' => $receipts->pluck('purchaseReceipt.document_number')->filter()->implode(', '),
            ];

            return $row;
        })->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    private function directInvoiceRows(array $filters): Collection
    {
        $query = PurchaseInvoiceLine::query()
            ->with(['postedPurchaseInvoice.vendor'])
            ->whereNull('po_line_id')
            ->whereNotNull('item_id');

        if ($filters['business_id']) {
            $query->whereHas('postedPurchaseInvoice', fn ($q) => $q->where('business_id', $filters['business_id']));
        }

        if ($filters['vendor_id']) {
            $query->whereHas('postedPurchaseInvoice', fn ($q) => $q->where('vendor_id', $filters['vendor_id']));
        }

        if ($filters['purchase_order_id']) {
            $query->whereHas('postedPurchaseInvoice', fn ($q) => $q->where('order_id', $filters['purchase_order_id']));
        }

        if ($filters['date_from']) {
            $query->whereHas('postedPurchaseInvoice', fn ($q) => $q->whereDate('posting_date', '>=', $filters['date_from']));
        }

        if ($filters['date_to']) {
            $query->whereHas('postedPurchaseInvoice', fn ($q) => $q->whereDate('posting_date', '<=', $filters['date_to']));
        }

        return $query->get()->map(function (PurchaseInvoiceLine $line): object {
            $invoice = $line->postedPurchaseInvoice;

            return (object) [
                'reference_type' => 'PURCHASE_INVOICE',
                'reference_number' => $invoice?->document_number ?? '—',
                'reference_url' => $invoice
                    ? PurchaseInvoiceResource::getUrl('view-posted', ['record' => $invoice], panel: 'admin')
                    : null,
                'order_id' => $invoice?->order_id,
                'vendor_id' => $invoice?->vendor_id,
                'vendor_number' => $invoice?->vendor?->vendor_code,
                'vendor_name' => $invoice?->vendor_name ?: $invoice?->vendor?->vendor_name,
                'item_code' => $line->item_code ?: $line->item?->item_code,
                'description' => $line->item_description ?: $line->item?->description ?: '—',
                'unit_of_measure_code' => $line->unit_of_measure_code,
                'line_number' => $line->line_number,
                'ordered_quantity' => null,
                'received_quantity' => null,
                'invoiced_quantity' => (float) $line->quantity,
                'remaining_to_receive' => null,
                'remaining_to_invoice' => null,
                'po_unit_cost' => null,
                'received_value' => null,
                'invoice_unit_cost' => (float) $line->unit_cost,
                'invoiced_value' => (float) $line->line_total,
                'quantity_variance' => null,
                'price_variance' => null,
                'amount_variance' => (float) $line->line_total,
                'match_status' => 'Direct Invoice / No Receipt Match',
                'invoice_document_number' => $invoice?->document_number,
                'receipt_document_number' => null,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function applyFilters(Collection $rows, array $filters): Collection
    {
        if (filled($filters['match_status'] ?? null)) {
            $rows = $rows->filter(fn (object $row): bool => $row->match_status === $filters['match_status']);
        }

        return $rows->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return array<string, mixed>
     */
    private function summary(Collection $rows): array
    {
        return [
            'total_rows' => $rows->count(),
            'matched_count' => $rows->where('match_status', 'Matched')->count(),
            'exception_count' => $rows->filter(fn (object $row): bool => ! in_array($row->match_status, ['Matched', 'Partially Received', 'Partially Invoiced'], true))->count(),
            'ordered_quantity' => round((float) $rows->sum('ordered_quantity'), 4),
            'received_quantity' => round((float) $rows->sum('received_quantity'), 4),
            'invoiced_quantity' => round((float) $rows->sum('invoiced_quantity'), 4),
            'ordered_value' => round((float) $rows->sum(fn (object $row): float => (float) ($row->ordered_quantity ?? 0) * (float) ($row->po_unit_cost ?? 0)), 4),
            'received_value' => round((float) $rows->sum(fn (object $row): float => (float) ($row->received_value ?? 0)), 4),
            'invoiced_value' => round((float) $rows->sum(fn (object $row): float => (float) ($row->invoiced_value ?? 0)), 4),
        ];
    }

    private function classifyLine(float $orderedQuantity, float $receivedQuantity, float $invoicedQuantity, float $poUnitCost, ?float $invoiceUnitCost): string
    {
        $tolerance = 0.0001;

        if (abs($receivedQuantity - $orderedQuantity) <= $tolerance && abs($invoicedQuantity - $orderedQuantity) <= $tolerance && ($invoiceUnitCost === null || abs($invoiceUnitCost - $poUnitCost) <= $tolerance)) {
            return 'Matched';
        }

        if ($receivedQuantity > $orderedQuantity + $tolerance) {
            return 'Over Received';
        }

        if ($invoicedQuantity > $receivedQuantity + $tolerance) {
            return 'Over Invoiced';
        }

        if ($invoiceUnitCost !== null && abs($invoiceUnitCost - $poUnitCost) > $tolerance && abs($receivedQuantity - $orderedQuantity) <= $tolerance) {
            return 'Price Variance';
        }

        if ($receivedQuantity < $orderedQuantity - $tolerance && $invoicedQuantity <= $receivedQuantity + $tolerance) {
            return 'Partially Received';
        }

        if ($invoicedQuantity < $receivedQuantity - $tolerance && $receivedQuantity >= $orderedQuantity - $tolerance) {
            return 'Partially Invoiced';
        }

        return 'Quantity Variance';
    }
}
