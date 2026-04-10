<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('changeStatus')
                ->label('Change Status')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->hasRole('super_admin'))
                ->form([
                    Select::make('status')
                        ->options(SalesOrderStatus::class)
                        ->default(fn (SalesOrder $record) => $record->status)
                        ->required()
                        ->native(false),
                ])
                ->action(function (SalesOrder $record, array $data) {
                    $record->update(['status' => $data['status']]);
                    \Filament\Notifications\Notification::make()
                        ->title('Status Updated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
