<?php

namespace App\Filament\Resources\ItemJournalTemplates\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemJournalTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold')
                            ->copyable(),

                        TextEntry::make('default_entry_type')
                            ->badge(),

                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),

                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),
                    ]),

                Grid::make(2)->schema([
                    Section::make('Posting Control')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('numberSeries.code')
                                ->label('No. Series'),
                            TextEntry::make('postingNumberSeries.code')
                                ->label('Posting No. Series')
                                ->placeholder('Same as No. Series'),
                            TextEntry::make('source_code')
                                ->placeholder('-'),
                            TextEntry::make('defaultInventoryAccount.account_number')
                                ->label('Inventory G/L')
                                ->formatStateUsing(fn($state, $record) => $state ? "{$state} – {$record->defaultInventoryAccount?->name}" : '-')
                                ->icon('heroicon-m-building-library'),
                        ]),

                    Section::make('Validation Flags')
                        ->columnSpan(1)
                        ->columns(2)
                        ->schema([
                            IconEntry::make('item_tracking_mandatory')->label('Tracking')->boolean(),
                            IconEntry::make('lot_mandatory')->label('Lot No.')->boolean(),
                            IconEntry::make('serial_no_mandatory')->label('Serial No.')->boolean(),
                            IconEntry::make('expiration_date_mandatory')->label('Expiration')->boolean(),
                            IconEntry::make('warehouse_location_mandatory')->label('Location')->boolean(),
                            IconEntry::make('bin_mandatory')->label('Bin')->boolean(),
                            IconEntry::make('check_warehouse_availability')->label('Availability')->boolean(),
                            IconEntry::make('allow_negative_inventory')->label('Neg. Inventory')->boolean(),
                        ]),
                ]),

                Section::make('System & Audit')
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('updated_at')->dateTime(),
                    ]),
            ]);
    }
}
