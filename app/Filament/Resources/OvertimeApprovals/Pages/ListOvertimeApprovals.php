<?php

namespace App\Filament\Resources\OvertimeApprovals\Pages;

use App\Filament\Resources\OvertimeApprovals\OvertimeApprovalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOvertimeApprovals extends ListRecords
{
    protected static string $resource = OvertimeApprovalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
