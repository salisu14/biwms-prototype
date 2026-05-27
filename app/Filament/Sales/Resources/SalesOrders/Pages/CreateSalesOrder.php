<?php

namespace App\Filament\Sales\Resources\SalesOrders\Pages;

use App\Filament\Sales\Resources\SalesOrders\SalesOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;
}
