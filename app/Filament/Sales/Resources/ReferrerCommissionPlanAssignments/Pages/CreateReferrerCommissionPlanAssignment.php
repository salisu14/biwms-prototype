<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\Pages;

use App\Filament\Resources\ReferrerCommissionPlanAssignments\Pages\CreateReferrerCommissionPlanAssignment as BaseCreateReferrerCommissionPlanAssignment;
use App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\ReferrerCommissionPlanAssignmentResource;

class CreateReferrerCommissionPlanAssignment extends BaseCreateReferrerCommissionPlanAssignment
{
    protected static string $resource = ReferrerCommissionPlanAssignmentResource::class;
}
