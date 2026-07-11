<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests\Pages;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceCorrectionRequest extends EditRecord
{
    protected static string $resource = AttendanceCorrectionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
