<?php

namespace App\Services\Purchase;

use App\Data\Purchase\ApprovePurchaseOrderData;
use App\Data\Purchase\CancelPurchaseOrderData;
use App\Data\Purchase\ClosePurchaseOrderData;
use App\Data\Purchase\CreatePurchaseOrderData;
use App\Data\Purchase\CreateReceiptData;
use App\Data\Purchase\PostInvoiceData;
use App\Data\Purchase\RecalculatePurchaseOrderTotalsData;
use App\Data\Purchase\UpdatePurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Models\NumberSeries;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\WarehouseReceipt;
use App\Services\PostingService;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected PostingService $postingService
    ) {}

    /**
     * Create a new Purchase Order with Lines
     */
    public function create(CreatePurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $orderNumber = $this->generateOrderNumber($data->orderType);

            // Resolve Vendor for default posting groups if not provided in Data object
            $vendor = Vendor::findOrFail($data->vendorId);

            $order = PurchaseOrder::create([
                'order_number' => $orderNumber,
                'order_type' => $data->orderType,
                'vendor_id' => $data->vendorId,
                'vendor_name' => $vendor->vendor_name,
                'order_date' => $data->orderDate ?? now(),
                'location_id' => $data->locationId,
                'posting_date' => $data->postingDate ?? now(),
                'due_date' => $data->dueDate,
                'delivery_date' => $data->deliveryDate,
                'payment_terms' => $data->paymentTerms ?? $vendor->payment_terms,
                'comment' => $data->comment,
                'created_by' => $data->createdBy,
                'status' => PurchaseOrderStatus::PENDING,
                // Ensure posting groups are captured at point of creation
                'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
                'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
                'vat_bus_posting_group' => $vendor->vat_bus_posting_group,
            ]);

            $this->syncLines($order, $data->lines);

            $order->recalculateTotals();

            return $order->load('lines');
        });
    }

    /**
     * Update existing Purchase Order
     * Handles Header updates and Line synchronization
     */
    public function update(UpdatePurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

            if (! $order->can_edit) {
                throw new Exception("Purchase Order {$order->order_number} is in a status that cannot be edited.");
            }

            $order->update([
                'comment' => $data->comment,
                'payment_terms' => $data->paymentTerms,
                'delivery_date' => $data->deliveryDate,
            ]);

            if (isset($data->lines)) {
                $this->syncLines($order, $data->lines);
            }

            $order->recalculateTotals();

            return $order->fresh('lines');
        });
    }

    /**
     * Internal method to handle line CRUD (Create, Update, Delete)
     */
    protected function syncLines(PurchaseOrder $order, array $linesData): void
    {
        $existingLineIds = $order->lines()->pluck('id')->toArray();
        $receivedIds = [];

        foreach ($linesData as $index => $line) {
            $lineId = $line['id'] ?? null;

            $attributes = [
                'line_number' => $index + 1,
                'item_id' => $line['item_id'],
                'variant_code' => $line['variant_code'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'unit_of_measure' => $line['unit_of_measure'],
                'vat_percentage' => $line['vat_percentage'] ?? 0,
                'discount_percentage' => $line['discount_percentage'] ?? 0,
            ];

            if ($lineId && in_array($lineId, $existingLineIds)) {
                $order->lines()->where('id', $lineId)->update($attributes);
                $receivedIds[] = $lineId;
            } else {
                $newLine = $order->lines()->create($attributes);
                $receivedIds[] = $newLine->id;
            }
        }

        // Delete lines that were not included in the update (Standard ERP behavior)
        $order->lines()->whereNotIn('id', $receivedIds)->delete();
    }

    /**
     * Approval Workflow
     *
     * @throws Exception
     */
    public function approve(ApprovePurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        if ($order->status !== PurchaseOrderStatus::PENDING) {
            throw new Exception('Only Pending orders can be approved.');
        }

        $order->approved_by = $data->approvedBy;
        $order->status = PurchaseOrderStatus::APPROVED;
        $order->save();

        return $order;
    }

    /**
     * Cancel Workflow
     *
     * @throws Exception
     */
    public function cancel(CancelPurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        if ($order->warehouseReceipts()->count() > 0 || $order->postedInvoices()->count() > 0) {
            throw new Exception('Cannot cancel an order that has existing receipts or invoices.');
        }

        $order->cancel();

        return $order;
    }

    /**
     * Close Workflow (Manual completion)
     */
    public function close(ClosePurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        $order->close();

        return $order;
    }

    /**
     * Reopen an approved order for modification
     */
    public function reopen(int $purchaseOrderId): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($purchaseOrderId);

        if ($order->warehouseReceipts()->count() > 0 || $order->postedInvoices()->count() > 0) {
            throw new Exception('Cannot reopen an order that has existing receipts or invoices.');
        }

        $order->update(['status' => PurchaseOrderStatus::PENDING]);

        return $order;
    }

    /**
     * Create Warehouse Receipt
     */
    public function createReceipt(CreateReceiptData $data): WarehouseReceipt
    {
        $order = PurchaseOrder::with('lines')->findOrFail($data->purchaseOrderId);

        if (! $order->can_receive) {
            throw new Exception('Purchase Order cannot be received in its current state.');
        }

        return DB::transaction(function () use ($order, $data) {
            $receipt = WarehouseReceipt::create([
                'document_number' => WarehouseReceipt::generateNumber(),
                'location_id' => $order->location_id,
                'source_document' => 'PURCHASE_ORDER',
                'source_document_id' => $order->id,
                'source_document_number' => $order->order_number,
                'vendor_id' => $order->vendor_id,
                'status' => 'OPEN',
                'assigned_user_id' => $data->userId,
                'receipt_date' => now(),
                'expected_receipt_date' => $order->delivery_date,
            ]);

            $hasLines = false;
            foreach ($order->lines as $line) {
                $qtyToReceive = $line->remaining_quantity; // Default to full remaining

                if ($qtyToReceive <= 0) {
                    continue;
                }

                $receipt->lines()->create([
                    'line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'variant_code' => $line->variant_code,
                    'description' => $line->description,
                    'quantity' => $qtyToReceive,
                    'unit_of_measure_code' => $line->unit_of_measure,
                    'source_line_id' => $line->id,
                ]);
                $hasLines = true;
            }

            if (! $hasLines) {
                throw new Exception('No remaining items to receive on this order.');
            }

            $order->update(['status' => PurchaseOrderStatus::PARTIALLY_RECEIVED]);

            return $receipt->load('lines');
        });
    }

    /**
     * Post Purchase Invoice
     * Moves totals and G/L logic here from the Model
     */
    public function postInvoice(PostInvoiceData $data): PurchaseInvoice
    {
        $order = PurchaseOrder::with(['lines', 'vendor'])->findOrFail($data->purchaseOrderId);

        return DB::transaction(function () use ($order, $data) {
            $invoice = PurchaseInvoice::create([
                'document_number' => $data->documentNumber ?? PurchaseInvoice::generateNumber(),
                'order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'posting_date' => $data->postingDate,
                'due_date' => $data->postingDate->addDays($order->payment_terms ?? 30),
                'general_business_posting_group_id' => $order->general_business_posting_group_id,
                'vendor_posting_group_id' => $order->vendor_posting_group_id,
                'vat_bus_posting_group' => $order->vat_bus_posting_group,
            ]);

            $totalAmount = 0;
            $totalVat = 0;

            foreach ($data->lines as $lineData) {
                $poLine = $order->lines->find($lineData['poLineId']);

                if (! $poLine) {
                    throw new Exception('Purchase Order Line not found.');
                }

                // Check for over-invoicing
                $remainingToInvoice = $poLine->quantity - $poLine->invoiced_quantity;
                if ($lineData['quantity'] > $remainingToInvoice) {
                    throw new Exception("Cannot invoice more than remaining quantity for item: {$poLine->description}");
                }

                $lineTotal = $lineData['quantity'] * $poLine->unit_cost;
                $vatAmount = $lineTotal * ($poLine->vat_percentage / 100);

                $invoice->lines()->create([
                    'line_number' => $poLine->line_number,
                    'po_line_id' => $poLine->id,
                    'item_id' => $poLine->item_id,
                    'description' => $poLine->description,
                    'quantity' => $lineData['quantity'],
                    'unit_cost' => $poLine->unit_cost,
                    'unit_of_measure' => $poLine->unit_of_measure,
                    'line_total' => $lineTotal,
                    'vat_amount' => $vatAmount,
                    'total_amount' => $lineTotal + $vatAmount,
                ]);

                // Update PO Line Quantity
                $poLine->increment('invoiced_quantity', $lineData['quantity']);

                // G/L Posting
                $this->postingService->postPurchaseLine(
                    vendor: $order->vendor,
                    item: $poLine->item,
                    quantity: $lineData['quantity'],
                    unitCost: $poLine->unit_cost,
                    lineTotal: $lineTotal,
                    postingDate: $data->postingDate,
                    documentNumber: $invoice->document_number,
                    description: $poLine->description
                );

                $totalAmount += $lineTotal;
                $totalVat += $vatAmount;
            }

            // Post A/P entry
            $this->postingService->postVendorPayable(
                vendor: $order->vendor,
                amount: $totalAmount + $totalVat,
                postingDate: $data->postingDate,
                documentNumber: $invoice->document_number
            );

            $invoice->update([
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
            ]);

            // Finalize order status
            $this->refreshOrderStatus($order);

            return $invoice;
        });
    }

    /**
     * Public utility to recalculate totals manually via Service
     */
    public function recalculateTotals(RecalculatePurchaseOrderTotalsData $data): void
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);
        $order->recalculateTotals();
    }

    /**
     * Logic to determine if order is Fully Invoiced/Received
     */
    protected function refreshOrderStatus(PurchaseOrder $order): void
    {
        $order->load('lines');
        $allInvoiced = $order->lines->every(fn ($l) => $l->invoiced_quantity >= $l->quantity);

        if ($allInvoiced) {
            $order->update(['status' => PurchaseOrderStatus::CLOSED]);
        } else {
            $order->update(['status' => PurchaseOrderStatus::INVOICED]);
        }
    }

    public static function generateOrderNumber(PurchaseOrderType $orderType): ?string
    {
        $series = NumberSeries::where('code', $orderType->seriesCode())
            ->where('is_active', true)
            ->first();

        if (! $series) {
            $year = date('Y');
            $count = PurchaseOrder::whereYear('created_at', $year)
                ->where('order_type', $orderType)
                ->count() + 1;

            return sprintf('%d-%s-%05d', $year, $orderType->code(), $count);
        }

        $series->checkYearReset();

        return $series->generateNumber();
    }
}
