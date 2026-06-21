<?php

namespace App\Filament\Resources\VatPostingSetups\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatPostingSetupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('VAT Matrix')
                    ->description('Define the VAT percentage for a combination of Business and Product groups.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('vat_business_posting_group_id')
                                ->label('VAT Business Posting Group')
                                ->relationship('vatBusinessPostingGroup', 'code')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('vat_product_posting_group_id')
                                ->label('VAT Product Posting Group')
                                ->relationship('vatProductPostingGroup', 'code')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    ]),

                Section::make('Calculation & Posting')
                    ->description('Specify the tax rate and the G/L accounts for sales and purchase tax.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('vat_percentage')
                                ->label('VAT %')
                                ->numeric()
                                ->required()
                                ->default(0)
                                ->suffix('%'),
                            Select::make('sales_vat_account_id')
                                ->label('Sales VAT Account')
                                ->relationship('salesVatAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('purchase_vat_account_id')
                                ->label('Purchase VAT Account')
                                ->relationship('purchaseVatAccount', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),
                    ]),
            ]);
    }
}
