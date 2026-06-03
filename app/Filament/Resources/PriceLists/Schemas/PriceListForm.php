<?php

namespace App\Filament\Resources\PriceLists\Schemas;

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
                                        titleAttribute: 'description',
                                        modifyQueryUsing: fn ($query) => $query->finishedGoods()->where('blocked', false)->orderBy('item_code')
                                    )
                                    ->searchable()
//                                    ->searchColumns(['item_code', 'description'])
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Item $record) => "{$record->item_code} — {$record->description}"
                                    )
                                    ->preload()
                                    ->required()
                                    ->live(),

                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Leave empty to apply to the entire Customer Group below.'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('customer_group_id')
                                    ->label('Customer Group')
                                    ->relationship('customerGroup', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->helperText('Leave empty to apply to all groups (or specific customer above).'),

                                // Note: business_id is typically handled automatically by multi-tenancy.
                                // If you need it explicitly, uncomment below:
                                // Select::make('business_id')->relationship('business', 'name')->required(),
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
