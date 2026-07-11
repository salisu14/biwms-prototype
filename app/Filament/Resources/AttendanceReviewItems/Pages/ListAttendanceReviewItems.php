<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceReviewItems\Pages;

use App\Filament\Resources\AttendanceReviewItems\AttendanceReviewItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceReviewItems extends ListRecords
{
    protected static string $resource = AttendanceReviewItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
