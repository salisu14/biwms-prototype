<?php

namespace App\Filament\Resources\ItemCharges\Schemas;

use App\Models\GeneralProductPostingGroup;
use App\Models\VatProductPostingGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ItemChargeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Charge Details')
                    ->description('Define the item charge code and its basic information.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('no')
                                    ->label('Charge No.')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(20)
                                    ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                    ->helperText('e.g., FREIGHT, INSURANCE, CUSTOMS'),

                                TextInput::make('description')
                                    ->label('Description')
                                    ->required()
                                    ->maxLength(100)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        // Auto-generate search description if empty
                                        $set('search_description', strtoupper($state));
                                    }),

                                TextInput::make('description_2')
                                    ->label('Description 2')
                                    ->maxLength(50)
                                    ->columnSpanFull(),

                                TextInput::make('search_description')
                                    ->label('Search Description')
                                    ->maxLength(100)
                                    ->helperText('Used for quick lookups in dropdowns and searches.'),
                            ]),
                    ]),

                Section::make('Posting Setup')
                    ->description('Define how this charge posts to the General Ledger and VAT.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('gen_prod_posting_group')
                                    ->label('Gen. Prod. Posting Group')
                                    ->options(
                                        GeneralProductPostingGroup::pluck('code', 'code')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Determines the G/L accounts for the charge.'),

                                Select::make('vat_prod_posting_group')
                                    ->label('VAT Prod. Posting Group')
                                    ->options(
                                        VatProductPostingGroup::pluck('code', 'code')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->helperText('Determines the VAT calculation for the charge.'),
                            ]),
                    ]),
            ]);
    }
}
