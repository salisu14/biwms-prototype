<?php

namespace App\Filament\Resources\BlanketPurchaseOrders\Pages;

use App\Filament\Resources\BlanketPurchaseOrders\BlanketPurchaseOrderResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBlanketPurchaseOrder extends EditRecord
{
    protected static string $resource = BlanketPurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('makeOrder')
                ->label('Make Purchase Order')
                ->color('success')
                ->icon('heroicon-m-plus-circle')
                ->requiresConfirmation()
                ->action(fn ($record) => $record->createPurchaseOrder()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
