<?php

namespace App\Filament\Resources\VatMasters\Schemas;

use App\Models\VatMaster;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatMasterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('VAT Identification')
                    ->description('Primary codes and naming for tax calculations.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('VAT Code')
                                ->required()
                                ->unique(ignoreRecord: true)
                                // Lock the field if the record already exists in the database
                                ->disabled(fn (?VatMaster $record) => $record !== null)
                                // Ensure the value is still sent to the database during creation
                                ->dehydrated()
                                ->placeholder('e.g., VAT20')
                                ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                                ->helperText('The code cannot be changed once the Vat product posting group is created.'),
                            TextInput::make('description')
                                ->required()
                                ->placeholder('e.g., Standard Rate (20%)'),
                        ]),
                    ]),

                Section::make('Financial Setup')
                    ->description('Define the tax rate and the corresponding G/L accounts for posting.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('percentage')
                                ->label('Tax Rate')
                                ->required()
                                ->numeric()
                                ->default(0)
                                ->suffix('%')
                                ->helperText('Enter the percentage value (e.g., 20.00)'),

                            Select::make('purchase_account_id')
                                ->label('Purchase VAT Account')
                                ->relationship('purchaseAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),

                            Select::make('sales_account_id')
                                ->label('Sales VAT Account')
                                ->relationship('salesAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
