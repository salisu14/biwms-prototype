<?php

namespace App\Filament\Resources\BlanketSalesOrders\Pages;

use App\Filament\Resources\BlanketSalesOrders\BlanketSalesOrderResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditBlanketSalesOrder extends EditRecord
{
    protected static string $resource = BlanketSalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('makeOrder')
                ->label('Make Sales Order')
                ->color('success')
                ->icon('heroicon-m-plus-circle')
                ->requiresConfirmation()
                ->action(fn ($record) => $record->createSalesOrder()),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
