<?php

namespace App\Filament\Resources\DepreciationBooks\Pages;

use App\Filament\Resources\DepreciationBooks\DepreciationBookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDepreciationBooks extends ListRecords
{
    protected static string $resource = DepreciationBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
