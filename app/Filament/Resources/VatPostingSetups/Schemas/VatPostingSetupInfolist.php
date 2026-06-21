<?php

namespace App\Filament\Resources\VatPostingSetups\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatPostingSetupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('VAT Matrix Configuration')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('vatBusinessPostingGroup.code')
                                ->label('VAT Business Posting Group'),
                            TextEntry::make('vatProductPostingGroup.code')
                                ->label('VAT Product Posting Group'),
                        ]),
                    ]),

                Section::make('Rates & Accounts')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('vat_percentage')
                                ->label('VAT Percentage')
                                ->suffix('%'),
                            TextEntry::make('salesVatAccount.name')
                                ->label('Sales VAT Account'),
                            TextEntry::make('purchaseVatAccount.name')
                                ->label('Purchase VAT Account'),
                        ]),
                    ]),
            ]);
    }
}
