<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Pages;

use App\Filament\Resources\AttendanceReviewItems\AttendanceReviewItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceReviewItem extends ViewRecord
{
    protected static string $resource = AttendanceReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
