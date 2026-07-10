<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeLeaveEntitlements\Pages;

use App\Filament\Resources\EmployeeLeaveEntitlements\EmployeeLeaveEntitlementResource;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeLeaveEntitlement extends EditRecord
{
    protected static string $resource = EmployeeLeaveEntitlementResource::class;
}
