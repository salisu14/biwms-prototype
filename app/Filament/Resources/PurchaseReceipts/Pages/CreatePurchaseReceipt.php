<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;
}
