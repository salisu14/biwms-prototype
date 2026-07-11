<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Pages;

use App\Filament\Resources\WorkforceRosterAssignments\WorkforceRosterAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceRosterAssignment extends EditRecord
{
    protected static string $resource = WorkforceRosterAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
