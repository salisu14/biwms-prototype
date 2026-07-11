<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatches\AttendancePayrollReviewBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendancePayrollReviewBatch extends ViewRecord
{
    protected static string $resource = AttendancePayrollReviewBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
