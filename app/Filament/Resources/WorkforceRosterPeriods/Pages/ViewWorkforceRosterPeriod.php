<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods\Pages;

use App\Filament\Resources\WorkforceRosterPeriods\WorkforceRosterPeriodResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceRosterPeriod extends ViewRecord
{
    protected static string $resource = WorkforceRosterPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
