<?php

namespace App\Filament\Resources\ItemLedgerEntries\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)->schema([
                    // Identity & Document Context
                    Section::make('Entry Details')
                        ->columnSpan(2)
                        ->columns(3)
                        ->schema([
                            TextEntry::make('entry_number')->label('Entry No.')->weight('bold'),
                            TextEntry::make('entry_type')->badge()->color('primary'),
                            TextEntry::make('posting_date')->date(),
                            TextEntry::make('document_number')->label('Doc. No.')->copyable(),
                            TextEntry::make('document_type'),
                            TextEntry::make('document_line_number')->label('Line No.'),
                        ]),

                    Section::make('Status')
                        ->columnSpan(1)
                        ->schema([
                            IconEntry::make('open')->boolean()->label('Is Open Entry'),
                            TextEntry::make('entry_date')->dateTime(),
                        ]),
                ]),

                Grid::make(2)->schema([
                    Section::make('Item & Location')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('item.item_code')->label('Item Code')->weight('bold'),
                            TextEntry::make('variant_code')->placeholder('-'),
                            TextEntry::make('location.name')->label('Location'),
                            TextEntry::make('bin_code')->placeholder('-'),
                        ]),

                    Section::make('Inventory Flow')
                        ->columns(2)
                        ->schema([
                            TextEntry::make('quantity')->numeric()->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                            TextEntry::make('remaining_quantity')->numeric(),
                            TextEntry::make('unit_of_measure_code')->label('UOM'),
                        ]),
                ]),

                Section::make('Financials & Accounting')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('cost_amount_actual')->money('NGN'),
                        TextEntry::make('cost_amount_expected')->money('NGN'),
                        TextEntry::make('purchase_amount_actual')->money('NGN'),
                        TextEntry::make('inventoryPostingGroup.code')->label('Inv. Posting Group'),
                        TextEntry::make('generalProductPostingGroup.code')->label('Gen. Prod. Group'),
                        TextEntry::make('generalBusinessPostingGroup.code')->label('Gen. Bus. Group'),
                    ]),

                Section::make('Tracking & References')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('serial_number')->placeholder('-'),
                        TextEntry::make('lot_number')->placeholder('-'),
                        TextEntry::make('expiration_date')->date()->placeholder('-'),
                        TextEntry::make('source_type')->placeholder('-'),
                        TextEntry::make('source_id')->placeholder('-'),
                    ]),
            ]);
    }
}
