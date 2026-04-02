<?php

namespace App\Filament\Resources\BlanketOrders\Pages;

use App\Filament\Resources\BlanketOrders\BlanketOrderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBlanketOrder extends EditRecord
{
    protected static string $resource = BlanketOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
