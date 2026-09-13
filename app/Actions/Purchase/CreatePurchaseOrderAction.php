<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\CreatePurchaseOrderData;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Support\PurchasingCurrency;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderAction
{
    public function execute(CreatePurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {

            $vendor = Vendor::findOrFail($data->vendorId);

            // Currency precedence: explicit choice > configured vendor currency > LCY.
            $explicitCurrency = strtoupper(trim((string) $data->currencyCode));
            $vendorCurrency = strtoupper(trim((string) $vendor->currency));
            $currencyCode = $explicitCurrency !== ''
                ? $explicitCurrency
                : ($vendorCurrency !== '' ? $vendorCurrency : PurchasingCurrency::LCY_CODE);
            $currencyFactor = $currencyCode === PurchasingCurrency::LCY_CODE ? 1 : $data->currencyFactor;

            $order = PurchaseOrder::create([
                'order_type' => $data->orderType,
                'vendor_id' => $data->vendorId,
                'vendor_name' => $vendor->vendor_name,
                'order_date' => $data->orderDate,
                'location_id' => $data->locationId,
                'posting_date' => $data->postingDate,
                'due_date' => $data->dueDate,
                'delivery_date' => $data->deliveryDate,
                'payment_terms' => $data->paymentTerms,
                'currency_code' => $currencyCode,
                'currency_factor' => $currencyFactor,
                'comment' => $data->comment,
                'created_by' => $data->createdBy,
            ]);

            foreach ($data->lines as $index => $line) {
                $order->lines()->create([
                    'line_number' => $index + 1,
                    'item_id' => $line->itemId,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unitCost,
                    'unit_of_measure' => $line->unitOfMeasure,
                    'vat_percentage' => $line->vatPercentage,
                ]);
            }

            $order->recalculateTotals();

            return $order->load('lines');
        });
    }
}
