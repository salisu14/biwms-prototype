<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Pages;

use App\Filament\Resources\AttendanceReviewPeriods\AttendanceReviewPeriodResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceReviewPeriod extends ViewRecord
{
    protected static string $resource = AttendanceReviewPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
