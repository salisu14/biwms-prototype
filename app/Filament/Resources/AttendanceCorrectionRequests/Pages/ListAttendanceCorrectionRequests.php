<?php

namespace App\Filament\Resources\AttendanceCorrectionRequests\Pages;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceCorrectionRequests extends ListRecords
{
    protected static string $resource = AttendanceCorrectionRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
