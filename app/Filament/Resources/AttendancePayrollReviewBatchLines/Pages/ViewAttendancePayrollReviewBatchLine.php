<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatchLines\AttendancePayrollReviewBatchLineResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendancePayrollReviewBatchLine extends ViewRecord
{
    protected static string $resource = AttendancePayrollReviewBatchLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
