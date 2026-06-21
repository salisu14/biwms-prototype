<?php

namespace App\Filament\Resources\ExpenseTransactions\Pages;

use App\Filament\Resources\ExpenseTransactions\ExpenseTransactionResource;
use App\Filament\Resources\ExpenseTransactions\Support\BuildsExpensePostSummary;
use App\Services\ExpenseService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditExpenseTransaction extends EditRecord
{
    use BuildsExpensePostSummary;

    protected static string $resource = ExpenseTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send_for_approval')
                ->label('Send for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->hidden(fn ($record) => $record->status !== 'open')
                ->requiresConfirmation()
                ->action(function ($record): void {
                    $record->update(['status' => 'pending_approval']);

                    Notification::make()
                        ->title('Sent for Approval')
                        ->success()
                        ->send();
                }),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('info')
                ->hidden(fn ($record) => ! in_array($record->status, ['open', 'pending_approval'], true))
                ->requiresConfirmation()
                ->action(function ($record): void {
                    $record->update(['status' => 'approved']);

                    Notification::make()
                        ->title('Expense Approved')
                        ->success()
                        ->send();
                }),
            Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->hidden(fn ($record) => $record->status !== 'approved')
                ->modalHeading('Validate and Post Expense')
                ->modalDescription(fn ($record): string => self::buildPostValidationSummary($record))
                ->requiresConfirmation()
                ->action(function ($record): void {
                    try {
                        app(ExpenseService::class)->post($record);
                        Notification::make()->title('Transaction Posted')->success()->send();
                    } catch (\Exception $e) {
                        Notification::make()->title('Posting Failed')->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
