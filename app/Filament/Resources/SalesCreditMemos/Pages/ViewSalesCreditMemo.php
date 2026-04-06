<?php

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesCreditMemo extends ViewRecord
{
    protected static string $resource = SalesCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
