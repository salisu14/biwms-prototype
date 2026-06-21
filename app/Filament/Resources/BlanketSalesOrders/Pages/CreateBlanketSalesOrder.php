<?php

namespace App\Filament\Resources\BlanketSalesOrders\Pages;

use App\Filament\Resources\BlanketSalesOrders\BlanketSalesOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBlanketSalesOrder extends CreateRecord
{
    protected static string $resource = BlanketSalesOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order_type'] = 'Sales';

        return $data;
    }
}
