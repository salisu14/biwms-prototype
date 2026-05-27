<?php

namespace App\Filament\Sales\Widgets;

use App\Models\SalesQuote;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OpenQuotesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SalesQuote::query()
                    ->whereIn('status', ['draft', 'sent', 'accepted'])
                    ->orderBy('quote_date', 'desc')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('quote_no')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quote_date')
                    ->date(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->date(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('USD'),
                Tables\Columns\TextColumn::make('status')
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
            ->recordActions([
                Action::make('view')
                    ->url(fn (SalesQuote $record): string => route('filament.sales.resources.sales-quotes.edit', $record))
                    ->icon('heroicon-m-eye'),
            ]);
    }
}
