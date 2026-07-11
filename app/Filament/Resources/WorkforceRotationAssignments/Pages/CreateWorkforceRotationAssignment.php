<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRotationAssignments\Pages;

use App\Filament\Resources\WorkforceRotationAssignments\WorkforceRotationAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceRotationAssignment extends CreateRecord
{
    protected static string $resource = WorkforceRotationAssignmentResource::class;
}
