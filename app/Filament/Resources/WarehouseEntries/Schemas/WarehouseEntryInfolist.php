<?php

namespace App\Filament\Resources\WarehouseEntries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WarehouseEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Entry Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('entry_timestamp')
                                ->label('Posting Date & Time')
                                ->dateTime(),
                            TextEntry::make('entry_type')
                                ->label('Movement Type')
                                ->badge()
                                ->color(fn ($state) => $state === 'positive' ? 'success' : 'danger'),
                            TextEntry::make('item.item_code')
                                ->label('Item Number')
                                ->weight('bold'),
                        ]),
                    ]),

                Section::make('Quantities & Valuation')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('quantity')
                                ->numeric(4)
                                ->weight('bold')
                                ->color(fn ($record) => $record->isPositive() ? 'success' : 'danger'),
                            TextEntry::make('unit_of_measure_code')
                                ->label('UOM'),
                            TextEntry::make('unit_cost')
                                ->money()
                                ->label('Unit Cost (LCY)'),
                            TextEntry::make('total_cost')
                                ->money()
                                ->label('Total Value')
                                ->weight('bold'),
                        ]),
                    ]),

                Section::make('Logistics & Tracking')
                    ->description('Details of the physical location and lot/serial identification.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('location.name')->label('Warehouse Location'),
                            TextEntry::make('zone.zone_code')->label('Zone'),
                            TextEntry::make('bin.bin_code')->label('Bin'),

                            TextEntry::make('lot_no')->label('Lot Number')->placeholder('-'),
                            TextEntry::make('serial_no')->label('Serial Number')->placeholder('-'),
                            TextEntry::make('expiration_date')->date()->placeholder('-'),
                        ]),
                    ]),

                Section::make('Traceability')
                    ->description('Links to the documents and sub-ledgers that generated this entry.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('document_type')->label('Source Document'),
                            TextEntry::make('document_no')->label('Document No.')->copyable(),
                            TextEntry::make('document_line_no')->label('Line Reference'),

                            TextEntry::make('activityLine.activity.no')
                                ->label('Warehouse Activity')
                                ->placeholder('Direct Adjustment')
                                ->icon('heroicon-m-clipboard-document-check')
                                ->color('primary'),
                            TextEntry::make('item_ledger_entry_id')
                                ->label('Item Ledger ID')
                                ->placeholder('-'),
                            TextEntry::make('created_by')
                                ->label('Posting User ID'),
                        ]),
                        TextEntry::make('description')
                            ->label('Posting Description')
                            ->placeholder('No description provided.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
