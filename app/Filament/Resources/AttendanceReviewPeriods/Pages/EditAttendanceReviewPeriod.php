<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewPeriods\Pages;

use App\Filament\Resources\AttendanceReviewPeriods\AttendanceReviewPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceReviewPeriod extends EditRecord
{
    protected static string $resource = AttendanceReviewPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
