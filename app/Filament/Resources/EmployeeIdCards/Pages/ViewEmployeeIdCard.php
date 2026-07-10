<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCards\Pages;

use App\Filament\Resources\EmployeeIdCards\EmployeeIdCardResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeIdCard extends ViewRecord
{
    protected static string $resource = EmployeeIdCardResource::class;
}
