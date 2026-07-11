<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Pages;

use App\Filament\Resources\WorkforceRosterHistories\WorkforceRosterHistoryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceRosterHistory extends ViewRecord
{
    protected static string $resource = WorkforceRosterHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
