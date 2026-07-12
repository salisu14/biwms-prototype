<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLocations\Pages;

use App\Filament\Resources\AttendanceLocations\AttendanceLocationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceLocation extends EditRecord
{
    protected static string $resource = AttendanceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
