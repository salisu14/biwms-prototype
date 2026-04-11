<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Data\Purchase\CreatePurchaseOrderData;
use App\Enums\PurchaseOrderType;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Services\Purchase\PurchaseOrderService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchaseOrder extends CreateRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $dto = new CreatePurchaseOrderData(
            orderType: PurchaseOrderType::from($data['order_type']), // ✅ FIX HERE
            vendorId: $data['vendor_id'],
            locationId: $data['location_id'],
            orderDate: Carbon::parse($data['order_date']),
            postingDate: Carbon::parse($data['posting_date']),
            dueDate: Carbon::parse($data['due_date']),
            deliveryDate: Carbon::parse($data['delivery_date']),
            paymentTerms: $data['payment_terms'] ?? null,
            comment: $data['comment'] ?? null,
            createdBy: auth()->id(),
            lines: $data['lines'] ?? []
        );

        return app(PurchaseOrderService::class)->create($dto);
    }
}
