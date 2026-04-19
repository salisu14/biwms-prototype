<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use App\Services\Approval\ApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseQuote extends ViewRecord
{
    protected static string $resource = PurchaseQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn ($record) => $record->isPendingApproval() === false)
                ->action(function ($record) {
                    app(ApprovalService::class)->submitForApproval($record);

                    Notification::make()
                        ->title('Submitted for approval')
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->approvalEntries()->where('status', 'created')
                    ->where(function ($q) { $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id()); })
                    ->exists())
                ->requiresConfirmation()
                ->action(function ($record) {
                    $entry = $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) { $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id()); })
                        ->orderBy('sequence_no')
                        ->first();

                    if (! $entry) {
                        Notification::make()->title('No pending approval')->danger()->send();
                        return;
                    }

                    app(ApprovalService::class)->approve($entry);

                    Notification::make()
                        ->title('Approved')
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->approvalEntries()->where('status', 'created')
                    ->where(function ($q) { $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id()); })
                    ->exists())
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $entry = $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) { $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id()); })
                        ->orderBy('sequence_no')
                        ->first();

                    if (! $entry) {
                        Notification::make()->title('No pending approval')->danger()->send();
                        return;
                    }

                    app(ApprovalService::class)->reject($entry, $data['reason']);

                    Notification::make()
                        ->title('Rejected')
                        ->success()
                        ->send();
                }),
        ];
    }
}
