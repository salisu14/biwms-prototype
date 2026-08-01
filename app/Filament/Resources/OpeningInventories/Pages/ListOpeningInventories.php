<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Pages;

use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpeningInventories extends ListRecords
{
    protected static string $resource = OpeningInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
