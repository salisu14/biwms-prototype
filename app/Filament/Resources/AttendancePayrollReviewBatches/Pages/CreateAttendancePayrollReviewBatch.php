<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatches\AttendancePayrollReviewBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendancePayrollReviewBatch extends CreateRecord
{
    protected static string $resource = AttendancePayrollReviewBatchResource::class;
}
