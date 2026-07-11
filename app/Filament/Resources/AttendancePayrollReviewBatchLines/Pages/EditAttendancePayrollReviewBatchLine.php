<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatchLines\AttendancePayrollReviewBatchLineResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendancePayrollReviewBatchLine extends EditRecord
{
    protected static string $resource = AttendancePayrollReviewBatchLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
