<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Pages;

use App\Filament\Resources\EmployeeWorkAvailabilities\EmployeeWorkAvailabilityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeWorkAvailability extends CreateRecord
{
    protected static string $resource = EmployeeWorkAvailabilityResource::class;
}
