<?php

namespace App\Filament\Resources\DiscountRules\Schemas;

use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class DiscountRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Discount Rule Details')
                    ->description('Define a discount percentage for a specific item and customer group.')
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
                                        fn (\App\Models\Item $record) => "{$record->item_code} — {$record->description}"
                                    )
                                    ->preload()
                                    ->required(),

                                Select::make('customer_group_id')
                                    ->label('Customer Group')
                                    ->relationship('customerGroup', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Leave empty to apply to all groups? (Adjust if logic requires validation)'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('discount_percent')
                                    ->label('Discount %')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->step(0.01),

                                DatePicker::make('start_date')
                                    ->label('Start Date')
                                    ->required()
                                    ->native(false)
                                    ->default(now())
                                    ->live()
                                    ->minDate(now()->subYear()) // Optional: prevent ancient start dates
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Auto-clear end date if it's before the new start date
                                        if ($get('end_date') && $state > $get('end_date')) {
                                            $set('end_date', null);
                                        }
                                    }),

                                DatePicker::make('end_date')
                                    ->label('End Date')
                                    ->native(false)
                                    ->minDate(fn(Get $get) => $get('start_date') ?? now())
                                    ->helperText('Leave empty for a perpetual discount.'),
                            ]),
                    ])
            ]);
    }
}
