<?php

namespace App\Filament\Resources\MaintenanceContractSchedules\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class MaintenanceContractSchedulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('maintenanceContract.contract_no')
                    ->label('Contract')
                    ->searchable()
                    ->description(fn ($record) => $record->maintenanceContract?->description),
                TextColumn::make('fixedAsset.fa_no')
                    ->label('Asset')
                    ->toggleable()
                    ->description(fn ($record) => $record->fixedAsset?->description),
                TextColumn::make('frequency')->badge(),
                TextColumn::make('next_service_date')->date()->sortable(),
                TextColumn::make('estimated_cost')
                    ->money(fn ($record) => $record->maintenanceContract?->currency_code ?: 'NGN')
                    ->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Filter::make('due')
                    ->query(fn ($query) => $query->whereDate('next_service_date', '<=', now())),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('complete_service')
                    ->label('Complete Service')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record): bool => (bool) $record->is_active)
                    ->action(function ($record): void {
                        $record->completeService(now());
                        Notification::make()->title('Dispatch marked as completed')->success()->send();
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
