<?php

namespace App\Filament\Sales\Widgets;

use App\Models\Customer;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentCustomersWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    protected static ?string $heading = 'Recently Added Customers';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('view')
                    ->url(fn (Customer $record): string => route('filament.sales.resources.customers.edit', $record))
                    ->icon('heroicon-m-eye'),
            ]);
    }
}
