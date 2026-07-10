<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCards\Pages;

use App\Filament\Resources\EmployeeIdCards\EmployeeIdCardResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIdCards extends ListRecords
{
    protected static string $resource = EmployeeIdCardResource::class;
}
