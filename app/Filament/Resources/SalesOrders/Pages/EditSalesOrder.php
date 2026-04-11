<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Traits\PreventsEditingPostedRecords;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    use PreventsEditingPostedRecords;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('submit_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn ($record) => $record->status === SalesOrderStatus::DRAFT)
                ->action(function ($record) {
                    $record->submitForApproval();
                    Notification::make()
                        ->title('Submitted for Approval')
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status === SalesOrderStatus::PENDING_APPROVAL &&
                    (auth()->user()?->can('approve:order') || auth()->user()?->hasRole('SUPER_ADMIN'))
                )
                ->requiresConfirmation()
                ->action(function ($record) {
                    $record->approve(auth()->id());
                    Notification::make()
                        ->title('Order Approved')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
