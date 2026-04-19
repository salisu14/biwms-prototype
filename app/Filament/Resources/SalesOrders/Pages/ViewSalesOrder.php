<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesOrder extends ViewRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('submit_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::DRAFT)
                ->action(function (SalesOrder $record) {
                    app(\App\Services\Approval\ApprovalService::class)->submitForApproval($record);
                    Notification::make()
                        ->title('Submitted for Approval')
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (SalesOrder $record) => $record->status === SalesOrderStatus::PENDING_APPROVAL &&
                    $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) { $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id()); })
                        ->exists()
                )
                ->requiresConfirmation()
                ->action(function (SalesOrder $record) {
                    $entry = $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) { $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id()); })
                        ->orderBy('sequence_no')
                        ->first();

                    if (! $entry) {
                        Notification::make()->title('No pending approval')->danger()->send();
                        return;
                    }

                    app(\App\Services\Approval\ApprovalService::class)->approve($entry);
                    Notification::make()
                        ->title('Order Approved')
                        ->success()
                        ->send();
                }),

            Action::make('changeStatus')
                ->label('Change Status')
                ->icon('heroicon-o-shield-check')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->hasRole('SUPER_ADMIN'))
                ->form([
                    Select::make('status')
                        ->options(SalesOrderStatus::class)
                        ->default(fn (SalesOrder $record) => $record->status)
                        ->required()
                        ->native(false),
                ])
                ->action(function (SalesOrder $record, array $data) {
                    $record->update(['status' => $data['status']]);
                    Notification::make()
                        ->title('Status Updated')
                        ->success()
                        ->send();
                }),
        ];
    }
}
