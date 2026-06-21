<?php

namespace App\Filament\Resources\ItemSkus\Schemas;

use App\Filament\Traits\HasSystemGeneratedField;
use App\Models\Item;
use App\Models\Location;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ItemSkuForm
{
    use HasSystemGeneratedField;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('SKU Definition')
                    ->description('Select an Item and Location to automatically generate the SKU.')
                    ->schema([
                        Select::make('item_id')
                            ->label('Item')
                            ->relationship(
                                name: 'item',
                                titleAttribute: 'item_code'
                            )
                            ->getOptionLabelFromRecordUsing(fn (Item $record): string => "{$record->item_code} - {$record->description}")
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                static::generateSkuCode($set, $get);
                            })
                            ->required(),

                        Select::make('location_id')
                            ->label('Location')
                            ->relationship(
                                name: 'location',
                                titleAttribute: 'code'
                            )
                            ->getOptionLabelFromRecordUsing(fn (Location $record): string => "{$record->code} - {$record->name}")
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                static::generateSkuCode($set, $get);
                            })
                            ->required(),

                        static::makeSystemGeneratedTextInput(
                            'sku_code',
                            'SKU Code',
                            'Built automatically from the selected item and location and cannot be changed.',
                            'Auto-generated from Item and Location'
                        ),

                        // ADDED: Barcode field
                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Scannable barcode for this SKU.'),
                    ]),

                Section::make('Inventory Parameters')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('reorder_point')
                                ->label('Reorder Point')
                                ->required()
                                ->numeric()
                                ->default(0)
                                ->suffix('qty'),

                            TextInput::make('safety_stock')
                                ->label('Safety Stock')
                                ->required()
                                ->numeric()
                                ->default(0)
                                ->suffix('qty'),

                            TextInput::make('lead_time_days')
                                ->label('Lead Time (Days)')
                                ->numeric()
                                ->default(0)
                                ->suffix('days'),
                        ]),
                    ]),

                Section::make('Validity & Status')
                    ->description('Control the lifecycle and activation of this SKU.')
                    ->schema([
                        Grid::make(2)->schema([
                            // ADDED: Effective Date
                            DatePicker::make('effective_date')
                                ->label('Effective Date')
                                ->native(false)
                                ->helperText('When this SKU becomes active.'),

                            // ADDED: Expiry Date
                            DatePicker::make('expiry_date')
                                ->label('Expiry Date')
                                ->native(false)
                                ->helperText('When this SKU expires.'),
                        ]),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->collapsible(),
            ]);
    }

    /**
     * Helper method to generate and preview the SKU code in the form
     */
    protected static function generateSkuCode(Set $set, Get $get): void
    {
        $itemId = $get('item_id');
        $locationId = $get('location_id');

        if ($itemId && $locationId) {
            $item = Item::find($itemId);
            $location = Location::find($locationId);

            if ($item && $location) {
                $skuCode = sprintf('%s-%s', $item->item_code, $location->code);
                $set('sku_code', $skuCode);
            } else {
                $set('sku_code', null);
            }
        } else {
            $set('sku_code', null);
        }
    }
}
