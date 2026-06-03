<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PricingMasterQuantityBreakForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Break Assignment')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('pricing_master_id')
                                    ->label('Pricing Master')
                                    ->relationship('pricingMaster', 'description') // Fallback, overridden below
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getOptionLabelFromRecordUsing(
                                        fn (\App\Models\PricingMaster $record) => "{$record->code} — {$record->description}"
                                    )
                                    ->getSearchResultsUsing(
                                        fn (string $search) => \App\Models\PricingMaster::where('code', 'like', "%{$search}%")
                                            ->orWhere('description', 'like', "%{$search}%")
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn ($pm) => [$pm->id => "{$pm->code} — {$pm->description}"])
                                    ),

                                TextInput::make('line_number')
                                    ->label('Line No.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(10000)
                                    ->step(10000)
                                    ->helperText('Determines the order of the breaks (e.g., 10000, 20000).'),
                            ]),
                    ]),

                Section::make('Quantity Tier')
                    ->description('Define the minimum and maximum quantities for this pricing tier.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('minimum_quantity')
                                    ->label('Min. Quantity')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.0001)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set, Get $get, $state) {
                                        // Auto-clear max qty if it becomes less than min qty
                                        if ($get('maximum_quantity') !== null && $state > $get('maximum_quantity')) {
                                            $set('maximum_quantity', null);
                                        }
                                    }),

                                TextInput::make('maximum_quantity')
                                    ->label('Max. Quantity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.0001)
                                    ->minValue(fn (Get $get) => $get('minimum_quantity'))
                                    ->helperText('Leave empty for unlimited (e.g., 1000+)'),

                                TextInput::make('unit_of_measure_code')
                                    ->label('Unit of Measure')
                                    ->maxLength(20),
                                // If you have a UnitOfMeasure model, replace with:
                                // Select::make('unit_of_measure_code')->relationship('uom', 'code')->searchable()
                            ]),
                    ]),

                Section::make('Pricing / Discount')
                    ->description('Define either an override unit price, a percentage discount, or a flat discount amount. The system will usually prioritize the unit price if multiple are filled.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->step(0.0001)
                                    ->minValue(0),

                                TextInput::make('discount_percent')
                                    ->label('Discount %')
                                    ->numeric()
                                    ->suffix('%')
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01),

                                TextInput::make('discount_amount')
                                    ->label('Discount Amount')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->step(0.0001)
                                    ->minValue(0),
                            ]),
                    ]),
            ]);
    }
}
