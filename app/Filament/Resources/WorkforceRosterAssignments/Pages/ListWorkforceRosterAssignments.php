<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Pages;

use App\Filament\Resources\WorkforceRosterAssignments\WorkforceRosterAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceRosterAssignments extends ListRecords
{
    protected static string $resource = WorkforceRosterAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
