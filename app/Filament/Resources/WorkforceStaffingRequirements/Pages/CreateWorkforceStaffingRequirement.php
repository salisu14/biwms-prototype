<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Pages;

use App\Filament\Resources\WorkforceStaffingRequirements\WorkforceStaffingRequirementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceStaffingRequirement extends CreateRecord
{
    protected static string $resource = WorkforceStaffingRequirementResource::class;
}
