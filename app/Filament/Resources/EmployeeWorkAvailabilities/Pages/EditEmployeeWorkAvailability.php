<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Pages;

use App\Filament\Resources\EmployeeWorkAvailabilities\EmployeeWorkAvailabilityResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeWorkAvailability extends EditRecord
{
    protected static string $resource = EmployeeWorkAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
