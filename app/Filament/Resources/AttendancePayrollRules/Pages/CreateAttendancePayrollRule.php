<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules\Pages;

use App\Filament\Resources\AttendancePayrollRules\AttendancePayrollRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendancePayrollRule extends CreateRecord
{
    protected static string $resource = AttendancePayrollRuleResource::class;
}
