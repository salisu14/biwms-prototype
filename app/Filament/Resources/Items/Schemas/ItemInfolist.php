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
                            TextEntry::make('profit_percent')
                                ->label('Profit Margin')
                                ->suffix('%')
                                ->placeholder('0'),
                        ]),

                    Section::make('Inventory Status')
                        ->columnSpan(1)
                        ->schema([
                            TextEntry::make('inventory')
                                ->label('Current Stock')
                                ->weight('bold')
                                ->color(fn ($state) => $state <= 0 ? 'danger' : 'success'),
                            TextEntry::make('base_unit_of_measure')
                                ->label('Unit of Measure'),
                            TextEntry::make('location.name')
                                ->label('Location')
                                ->placeholder('No Location Assigned'),
                        ]),

                    Section::make('Restrictions')
                        ->columnSpan(1)
                        ->schema([
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
