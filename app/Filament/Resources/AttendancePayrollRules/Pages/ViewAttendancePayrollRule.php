<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules\Pages;

use App\Filament\Resources\AttendancePayrollRules\AttendancePayrollRuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendancePayrollRule extends ViewRecord
{
    protected static string $resource = AttendancePayrollRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
