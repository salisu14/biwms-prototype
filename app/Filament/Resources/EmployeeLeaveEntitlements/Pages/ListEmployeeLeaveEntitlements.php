<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeLeaveEntitlements\Pages;

use App\Filament\Resources\EmployeeLeaveEntitlements\EmployeeLeaveEntitlementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeLeaveEntitlements extends ListRecords
{
    protected static string $resource = EmployeeLeaveEntitlementResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
