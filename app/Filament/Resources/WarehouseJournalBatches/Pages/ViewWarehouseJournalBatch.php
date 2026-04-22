<?php

namespace App\Filament\Resources\WarehouseJournalBatches\Pages;

use App\Enums\JournalBatchStatus;
use App\Filament\Resources\WarehouseJournalBatches\WarehouseJournalBatchResource;
use App\Services\Posting\WarehouseJournalPostingRoutine;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseJournalBatch extends ViewRecord
{
    protected static string $resource = WarehouseJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),

            Action::make('register')
                ->label('Register Journal')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->hidden(fn () => $this->record->status !== JournalBatchStatus::OPEN)
                ->requiresConfirmation()
                ->modalHeading('Register Warehouse Journal?')
                ->modalDescription('All lines will be registered as warehouse entries creating bin-level movements. This does not post to the General Ledger.')
                ->action(function () {
                    try {
                        app(WarehouseJournalPostingRoutine::class)->post($this->record);
                        $this->refreshFormData(['status']);

                        Notification::make()
                            ->title('Journal Registered')
                            ->body("Batch {$this->record->name} was registered successfully.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Registration Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
