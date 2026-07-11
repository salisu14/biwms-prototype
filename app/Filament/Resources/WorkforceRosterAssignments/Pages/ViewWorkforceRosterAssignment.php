<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Pages;

use App\Filament\Resources\WorkforceRosterAssignments\WorkforceRosterAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceRosterAssignment extends ViewRecord
{
    protected static string $resource = WorkforceRosterAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
