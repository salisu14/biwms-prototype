<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Schemas;

use App\Enums\ItemType;
use App\Models\Item;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class CustomerPriceOverrideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Price Override Details')
                    ->description('Define a special pricing rule for a specific customer and item combination.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('customer_id')
                                    ->label('Customer')
                                    ->relationship('customer', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->unique(
                                        table: 'customer_price_overrides',
                                        column: 'item_id',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get) =>
                                        $rule->where('customer_id', $get('customer_id')),
                                    )
                                    ->validationMessages([
                                        'unique' => 'This customer already has a price override for this item.',
                                    ]),

                                Select::make('item_id')
                                    ->label('Item')
                                    ->relationship(
                                        name: 'item',
                                        titleAttribute: 'description',
                                        // 🔑 This cleanly restricts the dropdown to only Finished Goods
                                        modifyQueryUsing: fn ($query) => $query->finishedGoods()
                                    )
                                    ->searchable()
                                    // 🔑 This allows searching by item_code AND description natively
//                                    ->searchColumns(['item_code', 'description'])
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->getOptionLabelFromRecordUsing(
                                    // Use item_code instead of no (matching your Item model)
                                        fn (Item $record) => "{$record->item_code} — {$record->description}"
                                    )
                                    ->unique(
                                        table: 'customer_price_overrides',
                                        column: 'item_id', // Must match the field being validated
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule, Get $get) =>
                                        $rule->where('customer_id', $get('customer_id')),
                                    )
                                    ->validationMessages([
                                        'unique' => 'This item already has a price override for this customer.',
                                    ]),
                            ]),

                        TextInput::make('override_price')
                            ->label('Override Price')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->minValue(0)
                            ->maxValue(9999999.99)
                            ->helperText('Enter the special price for this customer. Must be greater than or equal to 0.'),
                    ]),
            ]);
    }
}
