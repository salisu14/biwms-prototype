<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Pages;

use App\Filament\Resources\WorkforceRosterRoles\WorkforceRosterRoleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceRosterRole extends ViewRecord
{
    protected static string $resource = WorkforceRosterRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
