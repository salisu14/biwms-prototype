<?php

declare(strict_types=1);

namespace App\Filament\Resources\Picks\Pages;

use App\Filament\Resources\Picks\PickResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPicks extends ListRecords
{
    protected static string $resource = PickResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
