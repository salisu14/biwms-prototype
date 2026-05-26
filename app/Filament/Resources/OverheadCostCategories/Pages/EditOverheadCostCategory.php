<?php

namespace App\Filament\Resources\OverheadCostCategories\Pages;

use App\Filament\Resources\OverheadCostCategories\OverheadCostCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverheadCostCategory extends EditRecord
{
    protected static string $resource = OverheadCostCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
