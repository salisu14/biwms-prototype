<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Pages;

use App\Filament\Resources\WorkforceShiftSwapRequests\WorkforceShiftSwapRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkforceShiftSwapRequest extends EditRecord
{
    protected static string $resource = WorkforceShiftSwapRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
