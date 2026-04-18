<?php

namespace App\Filament\Resources\AccountSchedules\Pages;

use App\Filament\Resources\AccountSchedules\AccountScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountSchedules extends ListRecords
{
    protected static string $resource = AccountScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
