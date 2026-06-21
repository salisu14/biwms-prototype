<?php

namespace App\Filament\Resources\VatMasters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class VatMasterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('VAT Identification')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('code')
                                ->label('VAT Code')
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('description')
                                ->label('Description'),
                        ]),
                    ]),

                Section::make('Tax Configuration')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('percentage')
                                ->label('Tax Rate')
                                ->suffix('%')
                                ->badge()
                                ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                            TextEntry::make('tax_status')
                                ->label('Status')
                                ->state(fn ($record) => $record->percentage > 0 ? 'Taxable' : 'Exempt/Zero Rated')
                                ->color(fn ($state) => $state === 'Taxable' ? 'success' : 'gray'),
                        ]),
                    ]),

                Section::make('G/L Account Links')
                    ->description('Linked accounts for automatic tax posting.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('purchaseAccount.account_number')
                                ->label('Purchase VAT Account')
//                                ->description(fn ($record) => $record->purchaseAccount?->name)
                                ->icon('heroicon-m-arrow-down-left')
                                ->color('primary')
                                ->copyable(),
                            TextEntry::make('salesAccount.account_number')
                                ->label('Sales VAT Account')
//                                ->description(fn ($record) => $record->salesAccount?->name)
                                ->icon('heroicon-m-arrow-up-right')
                                ->color('success')
                                ->copyable(),
                        ]),
                    ]),

                Section::make('Audit Trail')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->label('Created')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Last Modified')
                                ->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
