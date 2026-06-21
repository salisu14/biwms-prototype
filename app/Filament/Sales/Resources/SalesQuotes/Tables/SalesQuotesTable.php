<?php

namespace App\Filament\Sales\Resources\SalesQuotes\Tables;

use App\Models\SalesQuote;
use App\Services\Sales\SalesQuoteService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('quote_no')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quote_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('valid_until')
                    ->date()
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'expired' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'accepted' => 'Accepted',
                        'rejected' => 'Rejected',
                        'expired' => 'Expired',
                    ]),
                Filter::make('valid_until')
                    ->schema([
                        DatePicker::make('expires_before'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query->when($data['expires_before'], fn ($q, $date) => $q->whereDate('valid_until', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('convert_to_order')
                    ->action(function (SalesQuote $record) {
                        $order = app(SalesQuoteService::class)->convertToOrder($record);

                        return redirect()->route('filament.sales.resources.sales-orders.edit', $order);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (SalesQuote $record) => in_array((string) $record->status->value, ['accepted', 'approved'], true))
                    ->color('success')
                    ->icon('heroicon-m-arrow-path'),

                Action::make('print')
                    ->url(fn (SalesQuote $record) => route('sales.quotes.print', $record))
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-printer'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
