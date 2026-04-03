<?php

namespace App\Filament\Resources\FixedAssets\Schemas;

use App\Models\Manufacturing\FixedAsset;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FixedAssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Asset Summary')
                    ->icon('heroicon-m-cube')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Asset Code')
                                    ->weight('bold')
                                    ->copyable(),
                                TextEntry::make('description')
                                    ->label('Description'),
                                TextEntry::make('asset_type')
                                    ->badge()
                                    ->color('info'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn ($state) => match($state) {
                                        'ACTIVE' => 'success',
                                        'DISPOSED' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Financial Valuation')
                            ->icon('heroicon-m-banknotes')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('acquisition_cost')
                                            ->money()
                                            ->label('Acquisition Cost'),
                                        TextEntry::make('acquisition_date')
                                            ->date()
                                            ->label('Acquisition Date'),
                                        TextEntry::make('accumulated_depreciation')
                                            ->money()
                                            ->color('danger')
                                            ->label('Total Deprec.'),
                                        TextEntry::make('net_book_value')
                                            ->money()
                                            ->weight('bold')
                                            ->color('primary')
                                            ->label('Net Book Value'),
                                        TextEntry::make('salvage_value')
                                            ->money()
                                            ->label('Salvage Value'),
                                    ]),
                            ]),

                        Section::make('Depreciation Profile')
                            ->icon('heroicon-m-calculator')
                            ->columnSpan(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('depreciation_method')
                                            ->label('Method')
                                            ->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title()),
                                        TextEntry::make('useful_life_years')
                                            ->suffix(' Years')
                                            ->label('Useful Life'),
                                        TextEntry::make('annual_depreciation_amount')
                                            ->money()
                                            ->label('Annual Amount'),
                                        TextEntry::make('depreciation_rate')
                                            ->suffix('%')
                                            ->label('Deprec. Rate'),
                                    ]),
                            ]),
                    ]),

                Section::make('Operational Data')
                    ->icon('heroicon-m-cog')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('annual_capacity_minutes')
                                    ->label('Capacity')
                                    ->suffix(' min/yr'),
                                TextEntry::make('efficiency_percent')
                                    ->label('Efficiency')
                                    ->suffix('%'),
                                TextEntry::make('total_square_footage')
                                    ->label('Footprint')
                                    ->suffix(' sqft'),
                            ]),
                    ])
                    ->collapsible(),
                Section::make('Record Information')
                    ->compact()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('creator.name')
                                    ->label('Created By'),
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Accounting Links')
                    ->icon('heroicon-m-link')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('assetAccount.code')
                                    ->label('Asset G/L')
                                    ->placeholder('Not Assigned'),
                                TextEntry::make('accumDepAccount.code')
                                    ->label('Accum. Deprec G/L'),
                                TextEntry::make('depExpenseAccount.code')
                                    ->label('Deprec. Expense G/L'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
