<?php

namespace App\Filament\Resources\OverheadCostCategories\Pages;

use App\Filament\Resources\OverheadCostCategories\OverheadCostCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOverheadCostCategories extends ListRecords
{
    protected static string $resource = OverheadCostCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
