<?php

namespace App\Filament\Resources\ReasonCodes\Pages;

use App\Filament\Resources\ReasonCodes\ReasonCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReasonCodes extends ListRecords
{
    protected static string $resource = ReasonCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
