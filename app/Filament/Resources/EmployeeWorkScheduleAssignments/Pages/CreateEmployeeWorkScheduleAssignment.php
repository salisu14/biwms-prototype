<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages;

use App\Filament\Resources\EmployeeWorkScheduleAssignments\EmployeeWorkScheduleAssignmentResource;
use App\Services\Hr\EmployeeWorkScheduleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateEmployeeWorkScheduleAssignment extends CreateRecord
{
    protected static string $resource = EmployeeWorkScheduleAssignmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(EmployeeWorkScheduleService::class)->create([
            ...$data,
            'created_by' => auth()->id(),
        ]);
    }
}
