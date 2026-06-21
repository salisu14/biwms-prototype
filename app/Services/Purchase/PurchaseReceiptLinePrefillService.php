<?php

namespace App\Services\Purchase;

use App\Enums\PurchaseLineType;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;

class PurchaseReceiptLinePrefillService
{
    public function prefillFromPurchaseOrder(PurchaseReceipt $purchaseReceipt): int
    {
        $purchaseReceipt->loadMissing([
            'lines',
            'purchaseOrder.location',
            'purchaseOrder.lines.item.vatProductPostingGroup',
            'purchaseOrder.lines.item.generalProductPostingGroup.defaultVatProductPostingGroup',
        ]);

        if (! $purchaseReceipt->purchase_order_id || $purchaseReceipt->lines->isNotEmpty()) {
            return 0;
        }

        $purchaseOrder = $purchaseReceipt->purchaseOrder;

        if ($purchaseOrder === null) {
            return 0;
        }

        $created = 0;
        $locationCode = $purchaseOrder->location?->code ?? $purchaseReceipt->location_code;

        foreach ($purchaseOrder->lines->sortBy('line_number') as $purchaseOrderLine) {
            $remainingQuantity = max(0, (float) $purchaseOrderLine->quantity - (float) $purchaseOrderLine->received_quantity);

            if ($remainingQuantity <= 0) {
                continue;
            }

            $lineType = $this->mapLineType($purchaseOrderLine);

            if ($lineType === null) {
                continue;
            }

            $unitOfMeasureCode = (string) ($purchaseOrderLine->unit_of_measure ?? '');
            $qtyPerUnitOfMeasure = $purchaseOrderLine->item?->getConversionFactorForUom($unitOfMeasureCode) ?? 1.0;
            $qtyPerUnitOfMeasure = $qtyPerUnitOfMeasure > 0 ? $qtyPerUnitOfMeasure : 1.0;
            $quantityBase = $remainingQuantity * $qtyPerUnitOfMeasure;
            $directUnitCost = (float) $purchaseOrderLine->unit_cost;
            $lineAmount = $remainingQuantity * $directUnitCost;
            $vatProductPostingGroupCode = $purchaseOrderLine->item?->vatProductPostingGroup?->code
                ?? $purchaseOrderLine->item?->generalProductPostingGroup?->defaultVatProductPostingGroup?->code;

            $purchaseReceipt->lines()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_line_id' => $purchaseOrderLine->id,
                'line_number' => $purchaseOrderLine->line_number,
                'type' => $lineType,
                'no' => $purchaseOrderLine->item_code,
                'description' => $purchaseOrderLine->description,
                'variant_code' => $purchaseOrderLine->variant_code,
                'unit_of_measure' => $unitOfMeasureCode,
                'unit_of_measure_code' => $unitOfMeasureCode,
                'quantity' => $remainingQuantity,
                'quantity_base' => $quantityBase,
                'quantity_received' => 0,
                'quantity_invoiced' => 0,
                'qty_received_base' => 0,
                'qty_invoiced_base' => 0,
                'qty_to_receive' => $remainingQuantity,
                'qty_to_invoice' => 0,
                'qty_to_assign' => 0,
                'qty_assigned' => 0,
                'qty_per_unit_of_measure' => $qtyPerUnitOfMeasure,
                'direct_unit_cost' => $directUnitCost,
                'unit_cost_lcy' => $directUnitCost,
                'line_amount' => $lineAmount,
                'line_discount_percent' => 0,
                'line_discount_amount' => 0,
                'inv_discount_amount' => 0,
                'allow_invoice_disc' => true,
                'location_code' => $locationCode,
                'shortcut_dimension_1_code' => $purchaseReceipt->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $purchaseReceipt->shortcut_dimension_2_code,
                'dimension_set_id' => $purchaseReceipt->dimension_set_id,
                'expected_receipt_date' => $purchaseOrderLine->expected_delivery_date,
                'planned_receipt_date' => $purchaseOrderLine->expected_delivery_date,
                'requested_receipt_date' => $purchaseReceipt->requested_receipt_date ?? $purchaseOrder->delivery_date,
                'promised_receipt_date' => $purchaseReceipt->promised_receipt_date ?? $purchaseOrder->delivery_date,
                'vat_prod_posting_group' => $vatProductPostingGroupCode,
                'vat_base_amount' => $lineAmount,
            ]);

            $created++;
        }

        return $created;
    }

    private function mapLineType(PurchaseOrderLine $purchaseOrderLine): ?string
    {
        return match ($purchaseOrderLine->type) {
            PurchaseLineType::ITEM => 'ITEM',
            PurchaseLineType::GL_ACCOUNT => 'GL',
            PurchaseLineType::CHARGE => 'CHARGE',
            default => null,
        };
    }
}
