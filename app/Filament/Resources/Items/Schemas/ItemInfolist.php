<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class ItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Item Overview')
                    ->schema([
                        Grid::make(3)->schema([
                            Group::make([
                                TextEntry::make('item_number')
                                    ->label('SKU / Number')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('description')
                                    ->size(TextSize::Large),
                            ])->columnSpan(2),

                            TextEntry::make('item_type')
                                ->badge()
                                ->formatStateUsing(fn (ItemType $state): string => $state->label())
                                ->color(fn (ItemType $state): string => $state->color())
                                ->icon(fn (ItemType $state): string => $state->icon()),
                        ]),
                    ]),

                Grid::make(3)->schema([
                    Section::make('Financials')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('unit_price')->money(),
                            TextEntry::make('unit_cost')->money(),
                            TextEntry::make('inventory_method')
                                ->label('Inventory Method')
                                ->badge(),
                        ]),

                    Section::make('Logistics')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('uom.uom_code')
                                ->label('Base Unit of Measure'),
                            TextEntry::make('sku.sku_code')
                                ->label('Default SKU/Variant'),
                            TextEntry::make('location.name')
                                ->label('Default Location')
                                ->placeholder('No Location Assigned'),
                        ]),

                    Section::make('Stock Status')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('inventory')
                                ->label('Current Stock')
                                ->weight('bold')
                                ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),
                            TextEntry::make('reorder_point')
                                ->label('Reorder Point'),
                            TextEntry::make('bin_code')
                                ->label('Bin Code'),
                        ]),
                ]),

                Section::make('Posting Groups & Configuration')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('generalProductPostingGroup.code')
                                ->label('Gen. Prod. Posting Group'),
                            TextEntry::make('inventoryPostingGroup.code')
                                ->label('Inventory Posting Group'),
                            TextEntry::make('vat.code')
                                ->label('VAT Configuration'),
                        ]),
                    ]),

                Section::make('Restrictions')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            IconEntry::make('blocked')->boolean()->label('Fully Blocked'),
                            IconEntry::make('sales_blocked')->boolean()->label('Sales Blocked'),
                            IconEntry::make('purchasing_blocked')->boolean()->label('Purchasing Blocked'),
                        ]),
                    ]),

                Section::make('System Information')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')->dateTime(),
                            TextEntry::make('updated_at')->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
