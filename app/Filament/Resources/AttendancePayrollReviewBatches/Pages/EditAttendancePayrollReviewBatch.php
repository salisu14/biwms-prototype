<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatches\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatches\AttendancePayrollReviewBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendancePayrollReviewBatch extends EditRecord
{
    protected static string $resource = AttendancePayrollReviewBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
