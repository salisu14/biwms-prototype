<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Pages;

use App\Filament\Resources\WorkforceShiftSwapRequests\WorkforceShiftSwapRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkforceShiftSwapRequests extends ListRecords
{
    protected static string $resource = WorkforceShiftSwapRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
