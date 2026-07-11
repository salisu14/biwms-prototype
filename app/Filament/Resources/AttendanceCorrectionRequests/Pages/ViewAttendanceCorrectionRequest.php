<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests\Pages;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceCorrectionRequest extends ViewRecord
{
    protected static string $resource = AttendanceCorrectionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
