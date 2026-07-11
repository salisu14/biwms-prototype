<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeWorkAvailabilities\Pages;

use App\Filament\Resources\EmployeeWorkAvailabilities\EmployeeWorkAvailabilityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeWorkAvailabilities extends ListRecords
{
    protected static string $resource = EmployeeWorkAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
