<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Schemas;

use App\Models\PricingMaster;
use App\Models\UnitOfMeasure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PricingMasterQuantityBreakForm
{
    private static function currencySymbol(?string $currencyCode): string
    {
        return match ($currencyCode) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => '₦',
        };
    }

    /**
     * @return array<string, string>
     */
    private static function unitOfMeasureOptions(?PricingMaster $pricingMaster): array
    {
        if (! $pricingMaster?->item) {
            return [];
        }

        $pricingMaster->loadMissing(['item.baseUom', 'item.uoms']);

        $options = $pricingMaster->item->uoms
            ->mapWithKeys(fn (UnitOfMeasure $uom): array => [
                $uom->uom_code => $uom->uom_code.($uom->description ? " — {$uom->description}" : ''),
            ])
            ->toArray();

        $baseUom = $pricingMaster->item->baseUom;

        if ($baseUom && ! array_key_exists($baseUom->uom_code, $options)) {
            $options[$baseUom->uom_code] = $baseUom->uom_code.($baseUom->description ? " — {$baseUom->description}" : '');
        }

        return $options;
    }

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
                                    ->relationship('pricingMaster', 'price_list_code')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->getSearchResultsUsing(
                                        fn (string $search) => PricingMaster::query()
                                            ->where(function ($query) use ($search) {
                                                $query->where('price_list_code', 'like', "%{$search}%")
                                                    ->orWhere('description', 'like', "%{$search}%");
                                            })
                                            ->limit(50)
                                            ->get()
                                            ->mapWithKeys(fn (PricingMaster $record) => [
                                                $record->id => "{$record->price_list_code} — {$record->description}",
                                            ])
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (PricingMaster $record) => "{$record->price_list_code} — {$record->description}"
                                    )
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('unit_of_measure_code', null);
                                    }),

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

                                Select::make('unit_of_measure_code')
                                    ->label('Unit of Measure')
                                    ->options(fn (Get $get): array => self::unitOfMeasureOptions(
                                        $get('pricing_master_id') ? PricingMaster::query()->with(['item.baseUom', 'item.uoms'])->find($get('pricing_master_id')) : null
                                    ))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->helperText('Only UoMs assigned to the selected pricing master item are shown.'),
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
                                    ->prefix(fn (Get $get) => self::currencySymbol(PricingMaster::query()->find($get('pricing_master_id'))?->currency_code))
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
                                    ->prefix(fn (Get $get) => self::currencySymbol(PricingMaster::query()->find($get('pricing_master_id'))?->currency_code))
                                    ->step(0.0001)
                                    ->minValue(0),
                            ]),
                    ]),
            ]);
    }
}
