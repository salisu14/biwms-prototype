<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Pages;

use App\Filament\Resources\WorkforceStaffingRequirements\WorkforceStaffingRequirementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceStaffingRequirement extends EditRecord
{
    protected static string $resource = WorkforceStaffingRequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
