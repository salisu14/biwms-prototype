<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceCorrectionRequests\Pages;

use App\Filament\Resources\AttendanceCorrectionRequests\AttendanceCorrectionRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceCorrectionRequest extends CreateRecord
{
    protected static string $resource = AttendanceCorrectionRequestResource::class;
}
