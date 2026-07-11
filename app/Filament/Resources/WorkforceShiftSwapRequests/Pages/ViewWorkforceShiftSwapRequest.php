<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Pages;

use App\Filament\Resources\WorkforceShiftSwapRequests\WorkforceShiftSwapRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkforceShiftSwapRequest extends ViewRecord
{
    protected static string $resource = WorkforceShiftSwapRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
