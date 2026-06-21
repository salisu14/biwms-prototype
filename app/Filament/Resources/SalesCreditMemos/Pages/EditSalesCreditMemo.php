<?php

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesCreditMemo extends EditRecord
{
    protected static string $resource = SalesCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
