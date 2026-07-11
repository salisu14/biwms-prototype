<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Pages;

use App\Filament\Resources\WorkforceStaffingRequirements\WorkforceStaffingRequirementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceStaffingRequirements extends ListRecords
{
    protected static string $resource = WorkforceStaffingRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
