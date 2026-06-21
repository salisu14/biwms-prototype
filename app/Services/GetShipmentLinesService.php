<?php

namespace App\Services;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesShipmentHeader;
use App\Models\SalesShipmentLine;
use Illuminate\Support\Collection;

/**
 * BC Codeunit 64 "Sales-Get Shipment" equivalent
 * Handles "Get Shipment Lines" functionality in Sales Invoices
 */
class GetShipmentLinesService
{
    /**
     * Retrieve shipment lines for invoicing (BC "Get Shipment Lines" action)
     *
     * @param  array  $filters  Optional filters (shipment_no, posting_date_from, etc.)
     */
    public function getShipmentLinesForInvoicing(string $customerNo, array $filters = []): Collection
    {
        $query = SalesShipmentLine::with('header')
            ->whereHas('header', function ($q) use ($customerNo) {
                $q->where('sell_to_customer_no', $customerNo);
            })
            ->where('qty_shipped_not_invoiced', '>', 0)
            ->where('type', '!=', ' '); // Skip comment lines

        if (! empty($filters['shipment_no'])) {
            $query->where('document_no', $filters['shipment_no']);
        }

        if (! empty($filters['item_no'])) {
            $query->where('no', $filters['item_no']);
        }

        return $query->get()->map(function ($line) {
            return [
                'shipment_no' => $line->document_no,
                'shipment_line_no' => $line->line_no,
                'order_no' => $line->order_no,
                'order_line_no' => $line->order_line_no,
                'type' => $line->type,
                'no' => $line->no,
                'description' => $line->description,
                'quantity_shipped' => $line->quantity,
                'quantity_invoiced' => $line->quantity_invoiced,
                'qty_to_invoice' => $line->qty_shipped_not_invoiced,
                'unit_price' => $line->unit_price,
                'line_discount_pct' => $line->line_discount_pct,
                'shipment_date' => $line->shipment_date,
                'location_code' => $line->location_code,
            ];
        });
    }

    /**
     * Copy selected shipment lines to Sales Invoice (BC "InsertLine" pattern)
     *
     * @param  array  $selectedLines  Array of ['shipment_no', 'shipment_line_no', 'qty_to_invoice']
     */
    public function copyShipmentLinesToInvoice(SalesInvoice $invoice, array $selectedLines): void
    {
        foreach ($selectedLines as $selection) {
            $shipmentLine = SalesShipmentLine::where('document_no', $selection['shipment_no'])
                ->where('line_no', $selection['shipment_line_no'])
                ->first();

            if (! $shipmentLine) {
                continue;
            }

            $qtyToInvoice = $selection['qty_to_invoice'] ?? $shipmentLine->qty_shipped_not_invoiced;

            // Validate quantity
            if ($qtyToInvoice > $shipmentLine->qty_shipped_not_invoiced) {
                throw new \InvalidArgumentException(
                    "Qty. to Invoice exceeds Qty. Shipped Not Invoiced for line {$shipmentLine->line_no}"
                );
            }

            // BC: OnAfterInsertLine event pattern
            $invoiceLine = $this->createInvoiceLineFromShipment($invoice, $shipmentLine, $qtyToInvoice);

            // Update shipment line with reference
            $shipmentLine->update([
                'quantity_invoiced' => $shipmentLine->quantity_invoiced + $qtyToInvoice,
                'qty_shipped_not_invoiced' => $shipmentLine->qty_shipped_not_invoiced - $qtyToInvoice,
            ]);
        }
    }

    private function createInvoiceLineFromShipment(
        SalesInvoice $invoice,
        SalesShipmentLine $shipmentLine,
        float $qtyToInvoice
    ): SalesInvoiceLine {

        return SalesInvoiceLine::create([
            'sales_invoice_id' => $invoice->id,
            'document_no' => $invoice->document_no,

            // Copy from shipment
            'type' => $shipmentLine->type,
            'no' => $shipmentLine->no,
            'variant_code' => $shipmentLine->variant_code,
            'description' => $shipmentLine->description,
            'description_2' => $shipmentLine->description_2,

            'quantity' => $qtyToInvoice,
            'qty_to_invoice' => $qtyToInvoice,

            // Pricing from shipment (historical)
            'unit_price' => $shipmentLine->unit_price,
            'line_discount_pct' => $shipmentLine->line_discount_pct,
            'unit_of_measure_code' => $shipmentLine->unit_of_measure_code,

            // Tracking references (BC Order No. flow)
            'order_no' => $shipmentLine->order_no,
            'order_line_no' => $shipmentLine->order_line_no,
            'shipment_no' => $shipmentLine->document_no,
            'shipment_line_no' => $shipmentLine->line_no,

            'location_code' => $shipmentLine->location_code,
            'dimension_set_id' => $shipmentLine->dimension_set_id,
        ]);
    }

    /**
     * BC: Undo Shipment functionality (reverse posting)
     *
     * @throws \Exception
     */
    public function undoShipment(SalesShipmentHeader $shipment): void
    {
        if ($shipment->isFullyInvoiced()) {
            throw new \InvalidArgumentException('Cannot undo shipment that has been fully invoiced');
        }

        DB::transaction(function () use ($shipment) {
            foreach ($shipment->lines as $line) {
                if ($line->quantity_invoiced > 0) {
                    throw new \InvalidArgumentException(
                        "Line {$line->line_no} has been partially invoiced"
                    );
                }

                // Restore order line quantities
                if ($line->salesOrderLine) {
                    $line->salesOrderLine->update([
                        'quantity_shipped' => $line->salesOrderLine->quantity_shipped - $line->quantity,
                        'qty_shipped_not_invoiced' => $line->salesOrderLine->qty_shipped_not_invoiced - $line->quantity,
                        'outstanding_quantity' => $line->salesOrderLine->outstanding_quantity + $line->quantity,
                    ]);
                }

                // Reverse item ledger entries
                $this->inventoryPosting->reverseShipmentItemEntries($line);
            }

            $shipment->delete(); // Or mark as cancelled
        });
    }
}
