<?php

namespace App\Filament\Resources\ExpenseTransactions\Tables;

use App\Services\ExpenseService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ExpenseTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_no')
                    ->label('Doc No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('amount_lcy')
                    ->label('Total (LCY)')
                    ->money('NGN')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('account_type')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('generalBusinessPostingGroup.code')
                    ->label('Bus. Group')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('generalProductPostingGroup.code')
                    ->label('Prod. Group')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'posted' => 'success',
                        'reversed' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('invoice_no')
                    ->label('Inv No.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('posting_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'posted' => 'Posted',
                        'reversed' => 'Reversed',
                    ]),
                SelectFilter::make('document_type')
                    ->options([
                        'invoice' => 'Invoice',
                        'credit_memo' => 'Credit Memo',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('post')
                    ->label('Post')
                    ->button()
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->hidden(fn ($record) => $record->status === 'posted')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(ExpenseService::class)->post($record);

                            Notification::make()
                                ->title('Transaction Posted')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Posting Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
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
