<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\Pages;

use App\Filament\Resources\ReferrerCommissionPlanAssignments\Pages\ViewReferrerCommissionPlanAssignment as BaseViewReferrerCommissionPlanAssignment;
use App\Filament\Sales\Resources\ReferrerCommissionPlanAssignments\ReferrerCommissionPlanAssignmentResource;

class ViewReferrerCommissionPlanAssignment extends BaseViewReferrerCommissionPlanAssignment
{
    protected static string $resource = ReferrerCommissionPlanAssignmentResource::class;
}
