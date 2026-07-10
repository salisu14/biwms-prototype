<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeLeaveEntitlements\Pages;

use App\Filament\Resources\EmployeeLeaveEntitlements\EmployeeLeaveEntitlementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeLeaveEntitlement extends CreateRecord
{
    protected static string $resource = EmployeeLeaveEntitlementResource::class;
}
