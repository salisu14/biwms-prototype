<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Pages;

use App\Filament\Resources\AttendanceReviewItems\AttendanceReviewItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceReviewItem extends CreateRecord
{
    protected static string $resource = AttendanceReviewItemResource::class;
}
