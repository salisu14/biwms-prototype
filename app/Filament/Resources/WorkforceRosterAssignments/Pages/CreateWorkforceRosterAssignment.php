<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterAssignments\Pages;

use App\Filament\Resources\WorkforceRosterAssignments\WorkforceRosterAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceRosterAssignment extends CreateRecord
{
    protected static string $resource = WorkforceRosterAssignmentResource::class;
}
