<?php

declare(strict_types=1);

namespace App\Filament\Resources\LeaveRequests\Pages;

use App\Filament\Resources\LeaveRequests\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Resources\Pages\EditRecord;

class EditLeaveRequest extends EditRecord
{
    protected static string $resource = LeaveRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! in_array($this->record->status, [LeaveRequest::STATUS_DRAFT, LeaveRequest::STATUS_SUBMITTED], true)) {
            abort(403);
        }

        return $data;
    }
}
