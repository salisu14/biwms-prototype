<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages;

use App\Filament\Resources\EmployeeWorkScheduleAssignments\EmployeeWorkScheduleAssignmentResource;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Services\Hr\EmployeeWorkScheduleService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEmployeeWorkScheduleAssignment extends EditRecord
{
    protected static string $resource = EmployeeWorkScheduleAssignmentResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var EmployeeWorkScheduleAssignment $record */
        return app(EmployeeWorkScheduleService::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
