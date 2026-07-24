<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReferrerCommissionPlanAssignments\Pages;

use App\Filament\Resources\ReferrerCommissionPlanAssignments\ReferrerCommissionPlanAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReferrerCommissionPlanAssignments extends ListRecords
{
    protected static string $resource = ReferrerCommissionPlanAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
