<?php

namespace App\Filament\Resources\ItemUomAssignments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ItemUomAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment Context')
                    ->description('Link a specific Item to an alternate Unit of Measure.')
                    ->columns(2)
                    ->schema([
                        Select::make('item_id')
                            ->relationship('item', 'item_code')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->item_code} - {$record->description}")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->helperText('Select the item master record.'),

                        Select::make('uom_id')
                            ->label('Unit of Measure')
                            ->relationship('uom', 'uom_code')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the target physical Unit of Measure (e.g. BOX, PLT).'),
                    ]),

                Section::make('Conversion Rules')
                    ->description('Set conversion metrics and use-case rules.')
                    ->columns(3)
                    ->schema([
                        Select::make('uom_type')
                            ->label('UOM Type')
                            ->options([
                                'BASE' => 'Base/Inventory',
                                'SALES' => 'Sales',
                                'PURCHASE' => 'Purchase',
                                'SHIPPING' => 'Shipping',
                                'REPORTING' => 'Reporting',
                                'ALTERNATE' => 'Alternate',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if ($state === 'BASE') {
                                    $set('conversion_factor', 1.000000);
                                    $set('is_default', true);
                                }
                            })
                            ->helperText('The business context where this assignment is utilized.'),

                        TextInput::make('conversion_factor')
                            ->label('Qty. per Unit of Measure')
                            ->numeric()
                            ->required()
                            ->default(1.000000)
                            ->disabled(fn (Get $get) => $get('uom_type') === 'BASE')
                            ->dehydrated()
                            ->helperText('How many of the Base UOM make up one of this unit? (e.g., 1 BOX = 12 PCS -> Factor = 12).'),

                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Optional sorting weight for dropdown displays.'),

                        Toggle::make('is_default')
                            ->label('Default for Type')
                            ->default(false)
                            ->disabled(fn (Get $get) => $get('uom_type') === 'BASE')
                            ->dehydrated()
                            ->helperText('If enabled, this unit is defaulted during operations of this type.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
