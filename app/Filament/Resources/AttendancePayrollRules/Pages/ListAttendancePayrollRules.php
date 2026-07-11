<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollRules\Pages;

use App\Filament\Resources\AttendancePayrollRules\AttendancePayrollRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendancePayrollRules extends ListRecords
{
    protected static string $resource = AttendancePayrollRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
