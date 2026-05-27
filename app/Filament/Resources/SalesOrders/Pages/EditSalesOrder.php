<?php

namespace App\Filament\Resources\SalesOrders\Pages;

use App\Enums\SalesOrderStatus;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Filament\Traits\PreventsEditingPostedRecords;
use App\Services\Approval\ApprovalService;
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
                ->visible(fn ($record) => auth()->user()?->can('update', $record) && $record->status === SalesOrderStatus::DRAFT)
                ->action(function ($record) {
                    app(ApprovalService::class)->submitForApproval($record);
                    Notification::make()
                        ->title('Submitted for Approval')
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => auth()->user()?->can('approve', $record) &&
                    $record->status === SalesOrderStatus::PENDING_APPROVAL
                )
                ->requiresConfirmation()
                ->action(function ($record) {
                    $entry = $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) {
                            $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                        })
                        ->orderBy('sequence_no')
                        ->first();

                    if (! $entry) {
                        Notification::make()->title('No pending approval')->danger()->send();

                        return;
                    }

                    app(ApprovalService::class)->approve($entry);
                    Notification::make()
                        ->title('Order Approved')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
