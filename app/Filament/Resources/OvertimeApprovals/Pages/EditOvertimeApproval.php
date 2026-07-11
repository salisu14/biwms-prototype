<?php

namespace App\Filament\Resources\OvertimeApprovals\Pages;

use App\Filament\Resources\OvertimeApprovals\OvertimeApprovalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOvertimeApproval extends EditRecord
{
    protected static string $resource = OvertimeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
