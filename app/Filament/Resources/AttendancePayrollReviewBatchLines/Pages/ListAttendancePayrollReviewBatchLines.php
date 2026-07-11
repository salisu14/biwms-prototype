<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendancePayrollReviewBatchLines\Pages;

use App\Filament\Resources\AttendancePayrollReviewBatchLines\AttendancePayrollReviewBatchLineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendancePayrollReviewBatchLines extends ListRecords
{
    protected static string $resource = AttendancePayrollReviewBatchLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
