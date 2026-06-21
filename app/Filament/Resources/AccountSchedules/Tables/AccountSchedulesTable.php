<?php

namespace App\Filament\Resources\AccountSchedules\Tables;

use Database\Seeders\CashFlowStatementAccountScheduleSeeder;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccountSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('lines_count')
                    ->label('Defined Rows')
                    ->counts('lines')
                    ->badge()
                    ->color('info'),

                TextColumn::make('created_at')
                    ->label('Created On')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('repairCashFlow')
                    ->label('Run/Repair Default Cash Flow')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn ($record): bool => $record->name === 'Default Cash Flow Statement')
                    ->requiresConfirmation()
                    ->modalDescription('This will restore the seeded rows for the Default Cash Flow Statement schedule.')
                    ->action(function (): void {
                        app(CashFlowStatementAccountScheduleSeeder::class)->run();

                        Notification::make()
                            ->title('Default Cash Flow Statement repaired')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
