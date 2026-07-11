<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Pages;

use App\Filament\Resources\EmployeeWorkAvailabilities\EmployeeWorkAvailabilityResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeWorkAvailability extends ViewRecord
{
    protected static string $resource = EmployeeWorkAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
