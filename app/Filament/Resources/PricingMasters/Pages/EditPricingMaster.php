<?php

namespace App\Filament\Resources\PricingMasters\Pages;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingMaster extends EditRecord
{
    protected static string $resource = PricingMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
