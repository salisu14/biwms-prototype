<?php

namespace App\Filament\Resources\OvertimeApprovals\Pages;

use App\Filament\Resources\OvertimeApprovals\OvertimeApprovalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOvertimeApproval extends ViewRecord
{
    protected static string $resource = OvertimeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
