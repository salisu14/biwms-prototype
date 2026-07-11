<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Pages;

use App\Filament\Resources\WorkforceRosterRoles\WorkforceRosterRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceRosterRoles extends ListRecords
{
    protected static string $resource = WorkforceRosterRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
