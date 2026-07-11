<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Pages;

use App\Filament\Resources\WorkforceRotationAssignments\WorkforceRotationAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceRotationAssignments extends ListRecords
{
    protected static string $resource = WorkforceRotationAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
