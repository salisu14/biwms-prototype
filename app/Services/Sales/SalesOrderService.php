<?php

namespace App\Services\Sales;

use App\Enums\SalesOrderStatus;
use App\Models\Item;
use App\Models\PostedSalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\WarehouseShipment;
use App\Models\WarehouseShipmentLine;
use App\Services\PostingService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function __construct(
        protected PostingService $postingService,
        protected PricingService $pricingService
    ) {}

    /**
     * Submit for approval
     */
    public function submitForApproval(SalesOrder $order): SalesOrder
    {
        if ($order->status !== SalesOrderStatus::DRAFT) {
            throw new \Exception('Only draft orders can be submitted');
        }

        $order->update([
            'status' => SalesOrderStatus::PENDING_APPROVAL,
        ]);

        return $order;
    }

    /**
     * Approve order
     */
    public function approve(SalesOrder $order, int $userId): SalesOrder
    {
        if (! $order->can_approve) {
            throw new \Exception('Order is not pending approval');
        }

        $order->update([
            'status' => SalesOrderStatus::APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $order;
    }

    /**
     * Release order to warehouse
     */
    public function release(SalesOrder $order): SalesOrder
    {
        if (! $order->can_release) {
            throw new \Exception('Order must be approved before release');
        }

        $order->update([
            'status' => SalesOrderStatus::RELEASED,
        ]);

        return $order;
    }

    /**
     * Cancel order
     */
    public function cancel(SalesOrder $order, int $userId, string $reason): SalesOrder
    {
        if (in_array($order->status, [
            SalesOrderStatus::SHIPPED,
            SalesOrderStatus::INVOICED,
            SalesOrderStatus::PARTIALLY_INVOICED,
        ])) {
            throw new \Exception('Cannot cancel shipped or invoiced order');
        }

        $order->update([
            'status' => SalesOrderStatus::CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $order;
    }

    /**
     * Add a line to the order with automatic pricing
     */
    public function addLine(
        SalesOrder $order,
        Item $item,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?\DateTime $requestedDeliveryDate = null
    ): SalesOrderLine {
        // Get price
        $priceData = $this->pricingService->getSalesPrice(
            item: $item,
            customer: $order->customer,
            quantity: $quantity,
            variantCode: $variantCode,
            uom: $uom ?? $item->base_unit_of_measure,
            location: $order->location,
            date: $order->order_date
        );

        $lineNumber = ($order->lines()->max('line_number') ?? 0) + 1;

        $line = $order->lines()->create([
            'line_number' => $lineNumber,
            'item_id' => $item->id,
            'item_code' => $item->item_number,
            'description' => $item->description,
            'variant_code' => $variantCode,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'quantity' => $quantity,
            'unit_of_measure_code' => $uom ?? $item->base_unit_of_measure,
            'qty_per_unit_of_measure' => 1, // Logic would look up conversion
            'quantity_base' => $quantity,
            'unit_price' => $priceData['unit_price'],
            'unit_cost' => $item->unit_cost,
            'line_discount_percent' => $priceData['discount_percent'],
            'requested_delivery_date' => $requestedDeliveryDate,
            'location_id' => $order->location_id,
            'price_source' => $priceData['price_source'],
            'pricing_master_id' => $priceData['pricing_master_id'],
        ]);

        $this->recalculateTotals($order);

        return $line;
    }

    /**
     * Calculate and update all totals
     */
    public function recalculateTotals(SalesOrder $order): void
    {
        SalesOrder::withoutEvents(function () use ($order) {
            $lines = $order->lines;

            $order->subtotal = $lines->sum('line_total');
            $order->line_discount_total = $lines->sum('line_discount_amount');

            $afterLineDiscounts = $lines->sum('line_amount');

            // Apply invoice discount
            if ($order->invoice_discount_percent) {
                $order->invoice_discount_amount = $afterLineDiscounts * ($order->invoice_discount_percent / 100);
            }

            $order->total_amount = $afterLineDiscounts - $order->invoice_discount_amount;
            $order->total_vat = $lines->sum('vat_amount'); // Simplified - should calc on discounted amount
            $order->grand_total = $order->total_amount + $order->total_vat;

            $order->save();
        });
    }

    /**
     * Create warehouse shipment from order
     */
    public function createShipment(SalesOrder $order, ?int $userId = null): WarehouseShipment
    {
        if (! $order->can_ship) {
            throw new \Exception("Order cannot be shipped. Status: {$order->status->value}");
        }

        return DB::transaction(function () use ($order, $userId) {
            $shipment = WarehouseShipment::create([
                'document_number' => WarehouseShipment::generateNumber(),
                'location_id' => $order->location_id,
                'source_document' => 'SALES_ORDER',
                'source_document_id' => $order->id,
                'source_document_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'status' => 'OPEN',
                'assigned_user_id' => $userId,
                'shipment_date' => $order->shipment_date ?? now(),
                'planned_delivery_date' => $order->promised_delivery_date,
            ]);

            foreach ($order->lines()->where('quantity_to_ship', '>', 0)->get() as $line) {
                $shipment->lines()->create([
                    'line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'variant_code' => $line->variant_code,
                    'description' => $line->description,
                    'quantity' => $line->quantity_to_ship,
                    'unit_of_measure_code' => $line->unit_of_measure_code,
                    'source_line_id' => $line->id,
                ]);
            }

            $order->update([
                'status' => SalesOrderStatus::PICKING,
                'assigned_warehouse_worker_id' => $userId,
            ]);

            return $shipment;
        });
    }

    /**
     * Post Sales Invoice (creates G/L entries)
     */
    public function postInvoice(
        SalesOrder $order,
        array $shipmentIds = [], // Specific shipments to invoice, or empty for all
        ?\DateTime $postingDate = null,
        ?string $documentNumber = null
    ): PostedSalesInvoice {
        if (! $order->can_invoice) {
            throw new \Exception('Sales Order cannot be invoiced. Status: '.$order->status->value);
        }

        $postingDate = $postingDate ?? now();

        return DB::transaction(function () use ($order, $shipmentIds, $postingDate, $documentNumber) {
            $invoice = PostedSalesInvoice::create([
                'document_number' => $documentNumber ?? PostedSalesInvoice::generateNumber(),
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer_name,
                'customer_address' => $order->customer_address,
                'ship_to_name' => $order->ship_to_name,
                'ship_to_address' => $order->ship_to_address,
                'general_business_posting_group_id' => $order->general_business_posting_group_id,
                'customer_posting_group_id' => $order->customer_posting_group_id,
                'vat_bus_posting_group' => $order->vat_bus_posting_group,
                'location_id' => $order->location_id,
                'shipping_agent_code' => $order->shipping_agent_code,
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'due_date' => $postingDate->copy()->addDays($order->payment_terms_code ?? 30),
                'shipment_date' => $order->shipment_date,
                'subtotal' => 0,
                'line_discount_total' => 0,
                'invoice_discount_amount' => $order->invoice_discount_amount,
                'total_amount' => 0,
                'total_vat' => 0,
                'grand_total' => 0,
                'currency_code' => $order->currency_code,
                'currency_factor' => $order->currency_factor,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'salesperson_id' => $order->salesperson_id,
            ]);

            $totalSubtotal = 0;
            $totalLineDiscount = 0;
            $totalVat = 0;

            // Get lines to invoice (from shipments or order)
            $linesToInvoice = $this->getLinesToInvoice($order, $shipmentIds);

            foreach ($linesToInvoice as $lineData) {
                $soLine = $lineData['so_line'];
                $quantity = $lineData['quantity'];

                $lineTotal = $quantity * $soLine->unit_price;
                $lineDiscount = $lineTotal * ($soLine->line_discount_percent / 100);
                $lineAmount = $lineTotal - $lineDiscount;
                $vatAmount = $lineAmount * ($soLine->vat_percentage / 100);

                // Calculate COGS
                $unitCost = $soLine->unit_cost ?? $soLine->item->unit_cost;
                $costAmount = $quantity * $unitCost;

                // Create invoice line
                $invLine = $invoice->lines()->create([
                    'so_line_id' => $soLine->id,
                    'so_line_number' => $soLine->line_number,
                    'item_id' => $soLine->item_id,
                    'item_code' => $soLine->item_code,
                    'item_description' => $soLine->description,
                    'variant_code' => $soLine->variant_code,
                    'general_product_posting_group_id' => $soLine->general_product_posting_group_id,
                    'inventory_posting_group_id' => $soLine->inventory_posting_group_id,
                    'quantity' => $quantity,
                    'unit_of_measure_code' => $soLine->unit_of_measure_code,
                    'qty_per_unit_of_measure' => $soLine->qty_per_unit_of_measure,
                    'quantity_base' => $quantity * $soLine->qty_per_unit_of_measure,
                    'unit_price' => $soLine->unit_price,
                    'unit_cost' => $unitCost,
                    'unit_cost_lcy' => $unitCost * $order->currency_factor,
                    'line_discount_percent' => $soLine->line_discount_percent,
                    'line_discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'line_amount' => $lineAmount,
                    'vat_code' => $soLine->vat_code,
                    'vat_percentage' => $soLine->vat_percentage,
                    'vat_amount' => $vatAmount,
                    'amount_including_vat' => $lineAmount + $vatAmount,
                    'cost_amount' => $costAmount,
                    'profit_amount' => $lineAmount - $costAmount,
                    'lot_number' => $lineData['lot_number'] ?? null,
                    'serial_number' => $lineData['serial_number'] ?? null,
                    'shipment_id' => $lineData['shipment_id'] ?? null,
                    'line_number' => $soLine->line_number,
                ]);

                // Post G/L entries for this line
                $glAccounts = $this->postingService->postSalesLine(
                    customer: $order->customer,
                    item: $soLine->item,
                    quantity: $quantity,
                    unitPrice: $soLine->unit_price,
                    lineDiscount: $lineDiscount,
                    lineAmount: $lineAmount,
                    costAmount: $costAmount,
                    postingDate: $postingDate,
                    documentNumber: $invoice->document_number,
                    description: $soLine->description
                );

                // Update invoice line with G/L accounts
                $invLine->update([
                    'sales_account_id' => $glAccounts['sales_account_id'],
                    'cogs_account_id' => $glAccounts['cogs_account_id'],
                    'inventory_account_id' => $glAccounts['inventory_account_id'],
                ]);

                // Update SO line progress
                $soLine->quantity_invoiced += $quantity;
                $soLine->line_status = $soLine->quantity_invoiced >= $soLine->quantity ? 'INVOICED' : 'PARTIALLY_INVOICED';
                $soLine->save();

                $totalSubtotal += $lineTotal;
                $totalLineDiscount += $lineDiscount;
                $totalVat += $vatAmount;
            }

            // Apply invoice discount proportionally
            $appliedInvoiceDiscount = 0;
            if ($order->invoice_discount_amount > 0 && $totalSubtotal > 0) {
                $discountRatio = $order->invoice_discount_amount / $order->subtotal;
                $appliedInvoiceDiscount = $totalSubtotal * $discountRatio;
            }

            $totalAmount = $totalSubtotal - $totalLineDiscount - $appliedInvoiceDiscount;

            // Post A/R entry (summary)
            $this->postingService->postCustomerReceivable(
                customer: $order->customer,
                amount: $totalAmount + $totalVat,
                postingDate: $postingDate,
                documentNumber: $invoice->document_number
            );

            // Update invoice totals
            $invoice->update([
                'subtotal' => $totalSubtotal,
                'line_discount_total' => $totalLineDiscount,
                'invoice_discount_amount' => $appliedInvoiceDiscount,
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
            ]);

            // Update order status
            $this->updateInvoiceStatus($order);

            return $invoice;
        });
    }

    /**
     * Get lines to invoice based on shipments
     */
    protected function getLinesToInvoice(SalesOrder $order, array $shipmentIds): array
    {
        $lines = [];

        if (empty($shipmentIds)) {
            // Invoice all shipped but uninvoiced quantities
            foreach ($order->lines as $soLine) {
                $qtyToInvoice = $soLine->quantity_shipped - $soLine->quantity_invoiced;
                if ($qtyToInvoice > 0) {
                    $lines[] = [
                        'so_line' => $soLine,
                        'quantity' => $qtyToInvoice,
                        'shipment_id' => null,
                        'lot_number' => null,
                        'serial_number' => null,
                    ];
                }
            }
        } else {
            // Get specific shipment lines
            $shipmentLines = WarehouseShipmentLine::whereIn('warehouse_shipment_id', $shipmentIds)
                ->whereHas('shipment', function ($q) use ($order) {
                    $q->where('source_document_id', $order->id)
                        ->where('source_document', 'SALES_ORDER');
                })
                ->get();

            foreach ($shipmentLines as $shLine) {
                $soLine = $order->lines()->find($shLine->source_line_id);
                if ($soLine) {
                    $lines[] = [
                        'so_line' => $soLine,
                        'quantity' => $shLine->quantity,
                        'shipment_id' => $shLine->warehouse_shipment_id,
                        'lot_number' => $shLine->lot_number,
                        'serial_number' => $shLine->serial_number,
                    ];
                }
            }
        }

        return $lines;
    }

    /**
     * Update order status based on shipment/invoice progress
     */
    protected function updateInvoiceStatus(SalesOrder $order): void
    {
        $allLines = $order->lines;
        $fullyInvoiced = $allLines->every(fn ($line) => $line->quantity_invoiced >= $line->quantity);
        $partiallyInvoiced = $allLines->some(fn ($line) => $line->quantity_invoiced > 0);
        $fullyShipped = $allLines->every(fn ($line) => $line->quantity_shipped >= $line->quantity);

        if ($fullyInvoiced) {
            $order->status = SalesOrderStatus::INVOICED;
            $order->fully_invoiced = true;
        } elseif ($partiallyInvoiced) {
            $order->status = SalesOrderStatus::PARTIALLY_INVOICED;
        } elseif ($fullyShipped) {
            $order->status = SalesOrderStatus::SHIPPED;
            $order->fully_shipped = true;
        }

        $order->quantity_invoiced = $allLines->sum('quantity_invoiced');
        $order->save();
    }
}
