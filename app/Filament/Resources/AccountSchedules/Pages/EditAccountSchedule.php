<?php

namespace App\Filament\Resources\AccountSchedules\Pages;

use App\Filament\Resources\AccountSchedules\AccountScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAccountSchedule extends EditRecord
{
    protected static string $resource = AccountScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
