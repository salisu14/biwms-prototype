<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterRoles\Pages;

use App\Filament\Resources\WorkforceRosterRoles\WorkforceRosterRoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceRosterRole extends EditRecord
{
    protected static string $resource = WorkforceRosterRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
