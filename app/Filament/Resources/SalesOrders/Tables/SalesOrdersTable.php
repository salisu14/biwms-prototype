<?php

namespace App\Filament\Resources\SalesOrders\Tables;

use App\Enums\SalesOrderStatus;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('order_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('grand_total')
                    ->money(fn ($record) => $record->currency_code)
                    ->sortable()
                    ->alignment('right'),

                IconColumn::make('fully_shipped')
                    ->label('Shipped')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('fully_invoiced')
                    ->label('Invoiced')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('salesperson.name')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(SalesOrderStatus::class),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
            ])
            ->recordActions([
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
