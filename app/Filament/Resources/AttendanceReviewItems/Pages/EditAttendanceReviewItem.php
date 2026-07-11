<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Pages;

use App\Filament\Resources\AttendanceReviewItems\AttendanceReviewItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceReviewItem extends EditRecord
{
    protected static string $resource = AttendanceReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
