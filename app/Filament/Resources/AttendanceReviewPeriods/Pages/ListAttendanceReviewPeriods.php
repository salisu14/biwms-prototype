<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Pages;

use App\Filament\Resources\AttendanceReviewPeriods\AttendanceReviewPeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceReviewPeriods extends ListRecords
{
    protected static string $resource = AttendanceReviewPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
