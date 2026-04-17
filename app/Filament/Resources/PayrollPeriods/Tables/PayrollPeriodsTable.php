<?php

namespace App\Filament\Resources\PayrollPeriods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class PayrollPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge(),
                \Filament\Tables\Columns\ToggleColumn::make('is_current'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Tables\Actions\Action::make('close')
                    ->label('Close Period')
                    ->icon('heroicon-o-lock-closed')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (\App\Models\PayrollPeriod $record) => $record->status !== \App\Enums\PayrollPeriodStatus::Closed)
                    ->action(function (\App\Models\PayrollPeriod $record, \Filament\Notifications\Notification $notification) {
                        try {
                            app(\App\Services\PayrollPeriodService::class)->close($record);
                            $notification->success()->title('Period Closed!')->body('YTD balances have been updated.')->send();
                        } catch (\Exception $e) {
                            $notification->danger()->title('Action Failed')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
