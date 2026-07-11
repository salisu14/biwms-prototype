<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Pages;

use App\Filament\Resources\WorkforceRotationAssignments\WorkforceRotationAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceRotationAssignment extends ViewRecord
{
    protected static string $resource = WorkforceRotationAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
