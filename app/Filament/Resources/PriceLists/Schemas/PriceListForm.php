<?php

namespace App\Filament\Resources\PriceLists\Schemas;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PriceListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price List Details')
                    ->description('Define special pricing for specific items and customers/groups.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('item_id')
                                    ->label('Item')
                                    ->relationship(
                                        name: 'item',
                                        titleAttribute: 'item_code',
                                        modifyQueryUsing: fn ($query) => $query->finishedGoods()->where('blocked', false)->orderBy('item_code')
                                    )
                                    ->searchable()
                                    ->getSearchResultsUsing(
                                        fn (string $search) => Item::query()
                                            ->finishedGoods()
                                            ->where('blocked', false)
                                            ->where(function ($query) use ($search) {
                                                $query->where('item_code', 'like', "%{$search}%")
                                                    ->orWhere('description', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (Item $record) => [
                                                $record->id => "{$record->item_code} — {$record->description}",
                                            ])
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Item $record) => "{$record->item_code} — {$record->description}"
                                    )
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->relationship('customer', 'customer_number')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('customer_group_id', null))
                                    ->getSearchResultsUsing(
                                        fn (string $search) => Customer::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where('customer_number', 'like', "%{$search}%")
                                                    ->orWhere('name', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (Customer $record) => [
                                                $record->id => "{$record->customer_number} — {$record->name}",
                                            ])
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Customer $record) => "{$record->customer_number} — {$record->name}"
                                    )
                                    ->helperText('Leave empty to apply to the entire Customer Group below.'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('customer_group_id')
                                    ->label('Customer Group')
                                    ->relationship('customerGroup', 'code')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('customer_id', null))
                                    ->getSearchResultsUsing(
                                        fn (string $search) => CustomerGroup::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where('code', 'like', "%{$search}%")
                                                    ->orWhere('name', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (CustomerGroup $record) => [
                                                $record->id => "{$record->code} — {$record->name}",
                                            ])
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (CustomerGroup $record) => "{$record->code} — {$record->name}"
                                    )
                                    ->helperText('Leave empty to apply to all groups (or specific customer above).'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('currency')
                                    ->label('Currency')
                                    ->options([
                                        'NGN' => 'NGN (₦)',
                                        'USD' => 'USD ($)',
                                        'EUR' => 'EUR (€)',
                                        'GBP' => 'GBP (£)',
                                    ])
                                    ->required()
                                    ->default('NGN')
                                    ->live(),

                                TextInput::make('price')
                                    ->label('Price')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(fn (Get $get) => match ($get('currency')) {
                                        'USD' => '$',
                                        'EUR' => '€',
                                        'GBP' => '£',
                                        default => '₦',
                                    })
                                    ->step(0.01),
                            ]),

                        Grid::make(2)
                            ->schema([
                                DatePicker::make('starting_date')
                                    ->label('Starting Date')
                                    ->required()
                                    ->native(false)
                                    ->default(now())
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Auto-clear end date if it's before the new start date
                                        if ($get('ending_date') && $state > $get('ending_date')) {
                                            $set('ending_date', null);
                                        }
                                    }),

                                DatePicker::make('ending_date')
                                    ->label('Ending Date')
                                    ->native(false)
                                    ->minDate(fn (Get $get) => $get('starting_date') ?? now())
                                    ->helperText('Leave empty for a perpetual price.'),
                            ]),
                    ]),
            ]);
    }
}
