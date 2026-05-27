<?php

namespace App\Filament\Resources\SalesQuotes\Tables;

use App\Enums\QuoteStatus;
use App\Models\SalesQuote;
use App\Services\Sales\SalesQuoteService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SalesQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quote_no')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quote_date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Expires')
                    ->date('M d, Y')
                    ->sortable()
                    ->color(fn ($record) => $record->valid_until?->isPast() ? 'danger' : null),

                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD') // Uses local currency formatting
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('status')
                    ->badge()
                    // Assuming QuoteStatus enum has a getLabel/getColor method
                    // If not, use match() logic here
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('approver.name')
                    ->label('Approved By')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(QuoteStatus::class),

                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('convert_to_order')
                    ->label('Convert to Order')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (SalesQuote $record): bool => auth()->user()->can('convert', $record) && $record->canConvertToOrder())
                    ->action(function (SalesQuote $record): void {
                        try {
                            app(SalesQuoteService::class)->convertToOrder($record);
                            Notification::make()
                                ->title('Sales quote converted to order')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title(collect($exception->errors())->flatten()->first() ?? 'Quote could not be converted.')
                                ->danger()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
