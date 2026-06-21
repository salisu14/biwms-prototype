<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\PurchaseOrder;
use App\Services\Purchase\PurchaseReceiptHeaderPrefillService;
use App\Services\Purchase\PurchaseReceiptLinePrefillService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $purchaseOrderId = (int) ($data['purchase_order_id'] ?? 0);

        if ($purchaseOrderId > 0) {
            $purchaseOrder = PurchaseOrder::query()->with(['vendor.contact', 'location'])->find($purchaseOrderId);

            if ($purchaseOrder) {
                $data = app(PurchaseReceiptHeaderPrefillService::class)->defaultsForPurchaseOrder($purchaseOrder, $data);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $createdLines = app(PurchaseReceiptLinePrefillService::class)->prefillFromPurchaseOrder($this->record);

        if ($this->record->purchase_order_id === null) {
            return;
        }

        Notification::make()
            ->title($createdLines > 0 ? 'Receipt lines copied from purchase order' : 'No remaining purchase order lines to copy')
            ->body($createdLines > 0
                ? "{$createdLines} line(s) were added from the selected purchase order."
                : 'All available purchase order quantities may already be fully received.')
            ->{$createdLines > 0 ? 'success' : 'warning'}()
            ->send();
    }
}
