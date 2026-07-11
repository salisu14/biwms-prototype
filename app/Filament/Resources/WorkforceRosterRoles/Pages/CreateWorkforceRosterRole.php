<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Pages;

use App\Filament\Resources\WorkforceRosterRoles\WorkforceRosterRoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkforceRosterRole extends CreateRecord
{
    protected static string $resource = WorkforceRosterRoleResource::class;
}
