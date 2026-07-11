<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods\Pages;

use App\Filament\Resources\WorkforceRosterPeriods\WorkforceRosterPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceRosterPeriod extends EditRecord
{
    protected static string $resource = WorkforceRosterPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
