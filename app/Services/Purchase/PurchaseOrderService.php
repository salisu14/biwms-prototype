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
use App\Enums\ItemLedgerEntryType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\WarehouseReceipt;
use App\Services\NumberSeriesService;
use App\Services\PostingService;
use App\Services\Warehouse\PutAwayWorksheetService;
use Exception;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(
        protected PostingService $postingService,
        protected PutAwayWorksheetService $putAwayService,
        protected PurchaseInvoiceService $purchaseInvoiceService,
        protected PurchasePriceCalculationService $purchasePriceCalculationService,
        protected NumberSeriesService $numberSeriesService
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
            $vendor = $order->vendor;
            $item = isset($line['item_id']) ? Item::find($line['item_id']) : null;
            $unitCost = (float) ($line['unit_cost'] ?? 0);

            if ($vendor && $item && $unitCost <= 0.0) {
                $priceInfo = $this->purchasePriceCalculationService->getUnitCost(
                    $vendor,
                    $item,
                    (float) ($line['quantity'] ?? 1),
                    $line['unit_of_measure'] ?? $item->base_unit_of_measure
                );

                $unitCost = (float) ($priceInfo['direct_unit_cost'] ?? 0);
            }

            $attributes = [
                'line_number' => $index + 1,
                'item_id' => $line['item_id'],
                'variant_code' => $line['variant_code'] ?? null,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_cost' => $unitCost,
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

                // Create Put-away activity
                $this->putAwayService->createPutAwayFromPurchase($line, $qtyToReceive);

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
     * Receive purchase order lines partially/full with strict quantity guards.
     *
     * @param  array<int, array{line_id?:int|null,line_number?:int|null,receive_qty?:float|int|string|null}>  $lines
     *
     * @throws Exception
     */
    public function receivePartial(int $purchaseOrderId, array $lines): PurchaseOrder
    {
        $order = PurchaseOrder::with('lines')->findOrFail($purchaseOrderId);

        if (! in_array($order->status, [PurchaseOrderStatus::PENDING, PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIALLY_RECEIVED], true)) {
            throw new Exception('Purchase Order cannot be received in its current state.');
        }

        return DB::transaction(function () use ($order, $lines): PurchaseOrder {
            $orderedLines = $order->lines->sortBy('line_number')->values();
            $receivedAny = false;
            $validationErrors = [];

            foreach (collect($lines)->values() as $index => $lineData) {
                $lineId = (int) ($lineData['line_id'] ?? 0);
                $lineNumber = (int) ($lineData['line_number'] ?? 0);
                $receiveQtyRaw = (string) ($lineData['receive_qty'] ?? 0);
                $receiveQty = (float) str_replace(',', '', $receiveQtyRaw);

                if ($receiveQty <= 0) {
                    continue;
                }

                $line = $lineId > 0
                    ? $order->lines->firstWhere('id', $lineId)
                    : null;

                if (! $line && $lineNumber > 0) {
                    $line = $order->lines->firstWhere('line_number', $lineNumber);
                }

                if (! $line && $orderedLines->has($index)) {
                    $line = $orderedLines->get($index);
                }

                if (! $line) {
                    continue;
                }

                $remaining = max(0, (float) $line->quantity - (float) $line->received_quantity);
                if ($receiveQty > $remaining) {
                    $validationErrors[] = "Receive quantity for item {$line->item_code} cannot exceed remaining quantity ({$remaining}).";

                    continue;
                }

                $line->update([
                    'received_quantity' => (float) $line->received_quantity + $receiveQty,
                ]);

                $receivedAny = true;
            }

            if ($validationErrors !== []) {
                throw new Exception(implode("\n", $validationErrors));
            }

            if (! $receivedAny) {
                throw new Exception('Enter a receive quantity greater than 0 for at least one line.');
            }

            $order->refresh()->load('lines');
            $allReceived = $order->lines->every(
                fn ($line): bool => (float) $line->received_quantity >= (float) $line->quantity
            );

            $order->update([
                'status' => $allReceived ? PurchaseOrderStatus::RECEIVED : PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ]);

            return $order->fresh('lines');
        });
    }

    public function postReceipt(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order): PurchaseOrder {
            /** @var PurchaseOrder $order */
            $order = PurchaseOrder::query()
                ->with('lines.item')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($order->status === PurchaseOrderStatus::CANCELLED) {
                throw new Exception('Cancelled purchase orders cannot be received.');
            }

            $receivedAny = false;

            foreach ($order->lines as $line) {
                $targetQuantity = (float) $line->received_quantity > 0
                    ? (float) $line->received_quantity
                    : (float) $line->quantity;

                if ($targetQuantity <= 0) {
                    continue;
                }

                $postedQuantityBase = (float) ItemLedgerEntry::query()
                    ->where('entry_type', ItemLedgerEntryType::PURCHASE)
                    ->where('document_type', 'PURCHASE_RECEIPT')
                    ->where('document_number', $order->order_number)
                    ->where('document_line_number', $line->line_number)
                    ->where('item_id', $line->item_id)
                    ->sum('quantity');
                $postedQuantity = $this->purchaseLineQuantityFromBase($line, $postedQuantityBase);

                $quantityToReceive = max(0.0, $targetQuantity - $postedQuantity);

                if ($quantityToReceive <= 0) {
                    if ((float) $line->received_quantity < $targetQuantity) {
                        $line->update(['received_quantity' => $targetQuantity]);
                    }

                    continue;
                }

                if ((float) $line->received_quantity < $targetQuantity) {
                    $line->update([
                        'received_quantity' => $targetQuantity,
                    ]);
                }

                if ($line->item?->isInventoryItem()) {
                    $this->createReceiptItemLedgerEntry($order, $line, $quantityToReceive);
                }

                $receivedAny = true;
            }

            if (! $receivedAny) {
                throw new Exception('Purchase receipt has already been posted for the received quantities.');
            }

            $order->refreshLifecycleStatus();

            return $order->fresh('lines');
        });
    }

    private function createReceiptItemLedgerEntry(PurchaseOrder $order, $line, float $quantity): ItemLedgerEntry
    {
        $item = $line->item;
        $quantityBase = $this->purchaseLineQuantityBase($line, $quantity);
        $locationId = $order->location_id ?? $item?->location_id;

        if (! $item || ! $item->isInventoryItem()) {
            throw new Exception("Item is missing for purchase receipt line {$line->id}.");
        }

        if (! $locationId) {
            throw new Exception("Location is missing for item {$item->item_code} on purchase receipt {$order->order_number}.");
        }

        $lineCost = $quantity * (float) $line->unit_cost;

        $entry = ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::PURCHASE,
            'document_type' => 'PURCHASE_RECEIPT',
            'document_number' => $order->order_number,
            'document_line_number' => $line->line_number,
            'item_id' => $item->id,
            'location_id' => $locationId,
            'quantity' => $quantityBase,
            'remaining_quantity' => $quantityBase,
            'open' => true,
            'posting_date' => $order->posting_date ?? now(),
            'entry_date' => now(),
            'source_id' => $order->id,
            'source_type' => PurchaseOrder::class,
            'cost_amount_actual' => $lineCost,
            'cost_amount_expected' => 0,
            'purchase_amount_actual' => $lineCost,
            'general_business_posting_group_id' => $order->general_business_posting_group_id,
            'general_product_posting_group_id' => $line->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
        ]);

        $item->increment('inventory', $quantityBase);

        return $entry;
    }

    private function purchaseLineQuantityBase($line, float $quantity): float
    {
        $conversionFactor = (float) ($line->qty_per_unit_of_measure ?? 0);

        if ($conversionFactor <= 0) {
            $conversionFactor = (float) ($line->item?->getConversionFactorForUom($line->unit_of_measure ?: $line->item?->base_unit_of_measure) ?? 1);
        }

        return $quantity * ($conversionFactor > 0 ? $conversionFactor : 1.0);
    }

    private function purchaseLineQuantityFromBase($line, float $quantityBase): float
    {
        $conversionFactor = (float) ($line->qty_per_unit_of_measure ?? 0);

        if ($conversionFactor <= 0) {
            $conversionFactor = (float) ($line->item?->getConversionFactorForUom($line->unit_of_measure ?: $line->item?->base_unit_of_measure) ?? 1);
        }

        return $quantityBase / ($conversionFactor > 0 ? $conversionFactor : 1.0);
    }

    /**
     * Post Purchase Invoice
     * Moves totals and G/L logic here from the Model
     */
    public function postInvoice(PostInvoiceData $data): PurchaseInvoice
    {
        $order = PurchaseOrder::query()->findOrFail($data->purchaseOrderId);
        $invoice = $this->purchaseInvoiceService->createFromOrder($order);
        $this->purchaseInvoiceService->post($invoice);

        return $invoice->fresh();
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
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            [$orderType->seriesCode()],
            null,
            'Purchase Order'
        );
    }
}
