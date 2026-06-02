<?php

namespace App\Filament\Resources\AccountSchedules\Pages;

use App\Filament\Resources\AccountSchedules\AccountScheduleResource;
use Database\Seeders\CashFlowStatementAccountScheduleSeeder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAccountSchedule extends EditRecord
{
    protected static string $resource = AccountScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('repairCashFlow')
                ->label('Run/Repair Default Cash Flow')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn (): bool => $this->record->name === 'Default Cash Flow Statement')
                ->requiresConfirmation()
                ->modalDescription('This will restore the seeded rows for the Default Cash Flow Statement schedule.')
                ->action(function (): void {
                    app(CashFlowStatementAccountScheduleSeeder::class)->run();
                    $this->record->refresh();

                    Notification::make()
                        ->title('Default Cash Flow Statement repaired')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
