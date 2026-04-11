<?php

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesCreditMemo extends CreateRecord
{
    protected static string $resource = SalesCreditMemoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = SalesOrderStatus::DRAFT;

        return $data;
    }
}
