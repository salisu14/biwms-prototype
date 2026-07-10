<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardVerificationLogs\Pages;

use App\Filament\Resources\EmployeeIdCardVerificationLogs\EmployeeIdCardVerificationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIdCardVerificationLogs extends ListRecords
{
    protected static string $resource = EmployeeIdCardVerificationLogResource::class;
}
