<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules\Pages;

use App\Filament\Resources\AttendancePayrollRules\AttendancePayrollRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendancePayrollRule extends EditRecord
{
    protected static string $resource = AttendancePayrollRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
