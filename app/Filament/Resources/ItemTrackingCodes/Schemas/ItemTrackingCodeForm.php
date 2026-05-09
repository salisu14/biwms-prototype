<?php

namespace App\Filament\Resources\ItemTrackingCodes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ItemTrackingCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->description('Primary identification for the tracking policy.')
                    ->columns(12)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20)
                            ->placeholder('e.g., SN-ALL')
                            ->columnSpan(4),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g., Full Serial Number Tracking')
                            ->columnSpan(8),
                    ]),

                Tabs::make('Tracking Configuration')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Methods')
                            ->icon('heroicon-m-finger-print')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Serial No.')
                                        ->description('Individual piece tracking.')
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('snspecific_tracking')
                                                ->label('SN Specific Tracking')
                                                ->helperText('Assigns a unique ID to every single unit.'),
                                        ]),

                                    Section::make('Lot No.')
                                        ->description('Batch level tracking.')
                                        ->columnSpan(1)
                                        ->schema([
                                            Toggle::make('lotspecific_tracking')
                                                ->label('Lot Specific Tracking')
                                                ->helperText('Track groups of items from the same production run.'),

                                            Toggle::make('lot_wholesale_tracking')
                                                ->label('Lot Wholesale Tracking')
                                                ->helperText('Allows mixing different lots in the same bin.'),
                                        ]),
                                ]),
                            ]),

                        Tabs\Tab::make('Expiration')
                            ->icon('heroicon-m-clock')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('man_expiration_date_entry_reqd')
                                        ->label('Man. Expiration Date Entry Required'),

                                    Toggle::make('man_expiration_date_on_receipt')
                                        ->label('Man. Expiration Date on Receipt'),

                                    Toggle::make('strict_expiration_posting')
                                        ->label('Strict Expiration Posting')
                                        ->helperText('System will block posting if the item is expired.'),

                                    Toggle::make('allow_expiration_correction')
                                        ->label('Allow Expiration Correction'),
                                ]),
                            ]),

                        Tabs\Tab::make('Inventory Flow')
                            ->icon('heroicon-m-arrows-right-left')
                            ->schema([
                                Grid::make(2)->schema([
                                    Section::make('Purchase Inbound/Outbound')
                                        ->schema([
                                            Toggle::make('lot_info_purchase_inbound')->label('Lot: Purchase Inbound'),
                                            Toggle::make('lot_info_purchase_outbound')->label('Lot: Purchase Outbound'),
                                            Toggle::make('sn_info_purchase_inbound')->label('SN: Purchase Inbound'),
                                            Toggle::make('sn_info_purchase_outbound')->label('SN: Purchase Outbound'),
                                        ]),
                                    Section::make('Sales Inbound/Outbound')
                                        ->schema([
                                            Toggle::make('lot_info_sales_inbound')->label('Lot: Sales Inbound'),
                                            Toggle::make('lot_info_sales_outbound')->label('Lot: Sales Outbound'),
                                            Toggle::make('sn_info_sales_inbound')->label('SN: Sales Inbound'),
                                            Toggle::make('sn_info_sales_outbound')->label('SN: Sales Outbound'),
                                        ]),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
