<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\Employee;
use App\Services\Hr\LeaveRequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(LeaveRequestService::class)->create(
            Employee::query()->findOrFail($data['employee_id']),
            $data
        );
    }
}
