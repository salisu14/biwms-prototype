<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Pages;

use App\Filament\Resources\WorkforceStaffingRequirements\WorkforceStaffingRequirementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceStaffingRequirement extends ViewRecord
{
    protected static string $resource = WorkforceStaffingRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
