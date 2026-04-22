<?php

namespace App\Filament\Resources\RecurringJournalBatches\Pages;

use App\Enums\JournalBatchStatus;
use App\Filament\Resources\RecurringJournalBatches\RecurringJournalBatchResource;
use App\Services\Posting\JournalPostingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewRecurringJournalBatch extends ViewRecord
{
    protected static string $resource = RecurringJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),

            Action::make('process')
                ->label('Process Now')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->hidden(fn () => $this->record->status !== JournalBatchStatus::OPEN)
                ->requiresConfirmation()
                ->modalHeading('Process Recurring Batch?')
                ->modalDescription('All active, due lines will be posted to the General Ledger and the recurring schedule will advance.')
                ->action(function () {
                    try {
                        app(JournalPostingService::class)->post($this->record);
                        $this->refreshFormData(['status']);

                        Notification::make()
                            ->title('Batch Processed')
                            ->body("Batch {$this->record->name} was processed successfully.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Processing Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
