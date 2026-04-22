<?php

namespace App\Filament\Resources\PaymentJournalLines\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PaymentJournalLinesTable
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

                TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->limit(25),

                TextColumn::make('amount_paid')
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

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->placeholder('—')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('payment_method_code')
                    ->label('Method')
                    ->badge()
                    ->color('info'),

                IconColumn::make('payment_processed')
                    ->label('Processed')
                    ->boolean(),
            ])
            ->defaultSort('due_date')
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

                TernaryFilter::make('payment_processed')->label('Processed'),
                TernaryFilter::make('exported_to_payment_jnl')->label('Exported'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn ($record) => $record->payment_processed),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->button()
                    ->hidden(fn ($record) => $record->payment_processed)
                    ->requiresConfirmation()
                    ->modalHeading('Post Vendor Payment?')
                    ->modalDescription('This will apply the payment to the selected invoice and post G/L entries (Dr Accounts Payable, Cr Bank Account).')
                    ->action(function ($record) {
                        try {
                            $record->applyPayment();

                            Notification::make()
                                ->title('Payment Posted')
                                ->body("Payment to vendor {$record->vendor->name} has been posted.")
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
