<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Pages;

use App\Filament\Resources\WorkforceRotationAssignments\WorkforceRotationAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceRotationAssignment extends EditRecord
{
    protected static string $resource = WorkforceRotationAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
