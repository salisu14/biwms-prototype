<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatchLines\AttendancePayrollReviewBatchLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendancePayrollReviewBatchLine extends CreateRecord
{
    protected static string $resource = AttendancePayrollReviewBatchLineResource::class;
}
