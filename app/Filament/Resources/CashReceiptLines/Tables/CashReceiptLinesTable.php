<?php

namespace App\Filament\Resources\CashReceiptLines\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CashReceiptLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('journalLine.posting_date')
                    ->label('Posting Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('journalLine.document_no')
                    ->label('Document No.')
                    ->searchable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('amount_received')
                    ->label('Amount')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->alignment('right')
                    ->sortable(),

                TextColumn::make('applies_to_doc_no')
                    ->label('Applied to Doc.')
                    ->placeholder('On Account')
                    ->searchable(),

                TextColumn::make('remaining_amount')
                    ->label('Unapplied')
                    ->numeric(decimalPlaces: 2)
                    ->prefix('$')
                    ->alignment('right')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success'),

                TextColumn::make('payment_method_code')
                    ->label('Method')
                    ->badge()
                    ->color('info'),

                TextColumn::make('bankAccount.name')
                    ->label('Bank Account')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('check_no')
                    ->label('Check No.')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('journalLine.posting_date', 'desc')
            ->filters([
                SelectFilter::make('payment_method_code')
                    ->label('Payment Method')
                    ->options([
                        'Cash' => 'Cash',
                        'Check' => 'Check',
                        'Bank Transfer' => 'Bank Transfer',
                        'Credit Card' => 'Credit Card',
                        'Electronic' => 'Electronic',
                    ])
                    ->native(false),

                SelectFilter::make('applies_to_doc_type')
                    ->label('Applied To')
                    ->options([
                        'Invoice' => 'Invoice',
                        'Credit Memo' => 'Credit Memo',
                        'Payment' => 'Payment',
                        'Refund' => 'Refund',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading('Post Cash Receipt?')
                    ->modalDescription('This will apply the payment to the selected invoice and post G/L entries (Dr Bank, Cr Accounts Receivable).')
                    ->action(function ($record) {
                        try {
                            $record->applyPayment();

                            Notification::make()
                                ->title('Receipt Posted')
                                ->body("Payment from customer {$record->customer->name} has been posted.")
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
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
