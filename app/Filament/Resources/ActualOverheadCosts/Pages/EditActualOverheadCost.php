<?php

namespace App\Filament\Resources\ActualOverheadCosts\Pages;

use App\Filament\Resources\ActualOverheadCosts\ActualOverheadCostResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditActualOverheadCost extends EditRecord
{
    protected static string $resource = ActualOverheadCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
