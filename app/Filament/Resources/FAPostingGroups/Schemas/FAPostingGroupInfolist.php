<?php

namespace App\Filament\Resources\FAPostingGroups\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FAPostingGroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Top Section: General Info & Status
                Grid::make(2)
                    ->schema([
                        Section::make('General Information')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextEntry::make('code')
                                        ->label('Code')
                                        ->weight('bold')
                                        ->size('lg')
                                        ->columnSpan(1),

                                    IconEntry::make('is_active')
                                        ->label('Active')
                                        ->boolean()
                                        ->columnSpan(1),

                                    TextEntry::make('description')
                                        ->label('Description')
                                        ->columnSpanFull()
                                        ->placeholder('No description provided.'),
                                ]),
                            ])->columnSpan(1),

                        Section::make('Applicability')
                            ->description('Asset types this posting group applies to.')
                            ->schema([
                                TextEntry::make('applicable_tangible_types')
                                    ->label('Tangible Types')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '-')
                                    ->badge(),

                                TextEntry::make('applicable_intangible_types')
                                    ->label('Intangible Types')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '-')
                                    ->badge(),

                                TextEntry::make('applicable_liquidity_types')
                                    ->label('Liquidity Types')
                                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : '-')
                                    ->badge(),
                            ])->columnSpan(1),
                    ]),

                // Middle Section: Posting Accounts (Acquisition & Depreciation)
                Grid::make(2)
                    ->schema([
                        Section::make('Acquisition')
                            ->description('Accounts for asset purchase and offset.')
                            ->schema([
                                TextEntry::make('acquisitionAccount.name')
                                    ->label('Acquisition Cost Account')
                                    ->icon('heroicon-m-currency-dollar')
                                    ->placeholder('Not Configured'),

                                // Assuming standard relationship naming for offset based on fillable
                                TextEntry::make('acquisitionCostOffsetAccount.name')
                                    ->label('Acquisition Cost Offset Account')
                                    ->icon('heroicon-m-banknotes')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),

                        Section::make('Depreciation')
                            ->description('Accounts for calculating depreciation expenses.')
                            ->schema([
                                TextEntry::make('depreciationAccount.name')
                                    ->label('Depreciation Account')
                                    ->icon('heroicon-m-chart-bar')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('depExpenseAccount.name')
                                    ->label('Depreciation Expense Account')
                                    ->icon('heroicon-m-receipt')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),
                    ]),

                // Middle Section: Maintenance & Disposal
                Grid::make(2)
                    ->schema([
                        Section::make('Maintenance')
                            ->schema([
                                TextEntry::make('maintenanceExpenseAccount.name')
                                    ->label('Maintenance Expense Account')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('maintenanceCostAccount.name')
                                    ->label('Maintenance Cost Account')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),

                        Section::make('Disposal')
                            ->description('Accounts used when selling or scrapping assets.')
                            ->schema([
                                TextEntry::make('disposalProceedsAccount.name')
                                    ->label('Disposal Proceeds Account')
                                    ->icon('heroicon-m-banknotes')
                                    ->placeholder('Not Configured'),

                                Grid::make(2)->schema([
                                    TextEntry::make('gainOnDisposalAccount.name')
                                        ->label('Gain Account')
                                        ->color('success')
                                        ->placeholder('-'),

                                    TextEntry::make('lossOnDisposalAccount.name')
                                        ->label('Loss Account')
                                        ->color('danger')
                                        ->placeholder('-'),
                                ]),
                            ])->columnSpan(1),
                    ]),

                // Bottom Section: Revaluation & Audit
                Grid::make(2)
                    ->schema([
                        Section::make('Revaluation')
                            ->schema([
                                TextEntry::make('appreciationAccount.name')
                                    ->label('Appreciation Account')
                                    ->placeholder('Not Configured'),

                                TextEntry::make('revaluationGainAccount.name')
                                    ->label('Revaluation Gain Account')
                                    ->placeholder('Not Configured'),
                            ])->columnSpan(1),

                        Section::make('System Details')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label('Last Updated')
                                    ->dateTime(),
                            ])->columnSpan(1),
                    ]),
            ]);
    }
}
