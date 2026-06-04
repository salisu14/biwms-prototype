<?php

namespace App\Filament\Resources\CustomerGroups\RelationManagers;

use App\Filament\Resources\PriceLists\PriceListResource;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Item;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PriceListsRelationManager extends RelationManager
{
    protected static string $relationship = 'priceLists';

    protected static ?string $relatedResource = PriceListResource::class;

    protected static ?string $title = 'Price Lists';

    public function table(Table $table): Table
    {
        $ownerRecord = $this->getOwnerRecord();

        $columns = match (true) {
            $ownerRecord instanceof Item => [
                TextColumn::make('customer.customer_number')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state, $record): string => $record->customer ? "{$record->customer->customer_number} - {$record->customer->name}" : 'All Customers')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->customer?->email ?? 'No customer email'),

                TextColumn::make('customerGroup.code')
                    ->label('Customer Group')
                    ->formatStateUsing(fn ($state, $record): string => $record->customerGroup ? "{$record->customerGroup->code} - {$record->customerGroup->name}" : 'All Groups')
                    ->toggleable()
                    ->searchable(),
            ],

            $ownerRecord instanceof Customer => [
                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->item?->description),

                TextColumn::make('customerGroup.code')
                    ->label('Customer Group')
                    ->formatStateUsing(fn ($state, $record): string => $record->customerGroup ? "{$record->customerGroup->code} - {$record->customerGroup->name}" : 'All Groups')
                    ->toggleable()
                    ->searchable(),
            ],

            $ownerRecord instanceof CustomerGroup => [
                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->item?->description),

                TextColumn::make('customer.customer_number')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state, $record): string => $record->customer ? "{$record->customer->customer_number} - {$record->customer->name}" : 'All Customers')
                    ->searchable()
                    ->description(fn ($record) => $record->customer?->email ?? 'No customer email'),
            ],

            default => [
                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->item?->description),

                TextColumn::make('customer.customer_number')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state, $record): string => $record->customer ? "{$record->customer->customer_number} - {$record->customer->name}" : 'All Customers')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('customerGroup.code')
                    ->label('Customer Group')
                    ->formatStateUsing(fn ($state, $record): string => $record->customerGroup ? "{$record->customerGroup->code} - {$record->customerGroup->name}" : 'All Groups')
                    ->searchable()
                    ->toggleable(),
            ],
        };

        return $table
            ->columns([
                ...$columns,

                TextColumn::make('price')
                    ->formatStateUsing(fn ($state, $record): string => Number::currency((float) $state, $record->currency))
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('starting_date')
                    ->label('Starts')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('ending_date')
                    ->label('Ends')
                    ->date('d/m/Y')
                    ->sortable()
                    ->default('No End'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($record): string => match (true) {
                        $record->ending_date && $record->ending_date < now() => 'danger',
                        $record->starting_date && $record->starting_date > now() => 'info',
                        default => 'success',
                    })
                    ->state(fn ($record): string => match (true) {
                        $record->ending_date && $record->ending_date < now() => 'Expired',
                        $record->starting_date && $record->starting_date > now() => 'Scheduled',
                        default => 'Active',
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
