<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\PurchaseOrder;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $purchaseOrderId = (int) ($data['purchase_order_id'] ?? 0);

        if ($purchaseOrderId > 0) {
            $purchaseOrder = PurchaseOrder::query()->with('vendor')->find($purchaseOrderId);

            if ($purchaseOrder) {
                $data['purchase_order_no'] = $data['purchase_order_no'] ?? $purchaseOrder->order_number;
                $data['vendor_id'] = $data['vendor_id'] ?? $purchaseOrder->vendor_id;
                $data['buy_from_vendor_name'] = $data['buy_from_vendor_name'] ?? $purchaseOrder->vendor_name;
                $data['pay_to_vendor_no'] = $data['pay_to_vendor_no'] ?? ($purchaseOrder->vendor?->vendor_code ?? null);
            }
        }

        return $data;
    }
}
